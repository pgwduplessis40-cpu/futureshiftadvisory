type QueueKind = 'questionnaire' | 'document-upload';

type EncryptedPayload = {
    iv: string;
    data: string;
};

type QueueRecord = {
    id: string;
    kind: QueueKind;
    url: string;
    dedupeKey: string;
    createdAt: string;
    attempts: number;
    payload: EncryptedPayload;
};

type QuestionnaireQueuePayload = {
    body: Record<string, unknown>;
};

type DocumentUploadQueuePayload = {
    fields: Record<string, string>;
    file: {
        name: string;
        type: string;
        lastModified: number;
        data: string;
    };
};

type QueuePayload = QuestionnaireQueuePayload | DocumentUploadQueuePayload;

type BackgroundSyncRegistration = ServiceWorkerRegistration & {
    sync?: {
        register: (tag: string) => Promise<void>;
    };
};

const DB_NAME = 'fsa-portal-offline-v1';
const STORE_NAME = 'queue';
const KEY_STORAGE = 'fsa.portal.offline.key.v1';
const SYNC_MESSAGE = 'PORTAL_OFFLINE_SYNC';
const QUEUE_CHANGED_EVENT = 'portal-offline-queue-changed';

let registrationStarted = false;
let flushing = false;

export function registerPortalOffline(): void {
    if (typeof window === 'undefined' || registrationStarted) {
        return;
    }

    registrationStarted = true;

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker
            .register('/sw.js')
            .then((registration) => {
                void registerBackgroundSync(registration);
            })
            .catch(() => null);

        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data?.type === SYNC_MESSAGE) {
                void flushPortalOfflineQueue();
            }
        });
    }

    window.addEventListener('online', () => {
        void flushPortalOfflineQueue();
        navigator.serviceWorker.controller?.postMessage({
            type: SYNC_MESSAGE,
        });
    });

    if (navigator.onLine) {
        void flushPortalOfflineQueue();
    }
}

export async function queueQuestionnaireSubmission(
    url: string,
    body: Record<string, unknown>,
): Promise<string> {
    const cleanBody = stripLocalDocumentIds(body);
    const dedupeKey = await stableHash({
        kind: 'questionnaire',
        url,
        body: cleanBody,
    });

    return queueRecord('questionnaire', url, dedupeKey, {
        body: cleanBody,
    });
}

export async function queueDocumentUpload({
    url,
    file,
    fields,
}: {
    url: string;
    file: File;
    fields: Record<string, string>;
}): Promise<{ id: string; original_filename: string }> {
    const data = await fileToBase64(file);
    const dedupeKey = await stableHash({
        kind: 'document-upload',
        url,
        file: {
            name: file.name,
            size: file.size,
            lastModified: file.lastModified,
        },
        fields,
    });
    const id = await queueRecord('document-upload', url, dedupeKey, {
        fields,
        file: {
            name: file.name,
            type: file.type,
            lastModified: file.lastModified,
            data,
        },
    });

    return {
        id: `offline:${id}`,
        original_filename: file.name,
    };
}

export async function flushPortalOfflineQueue(): Promise<{
    synced: number;
    remaining: number;
}> {
    if (
        flushing ||
        typeof navigator === 'undefined' ||
        !navigator.onLine ||
        !offlineStorageAvailable()
    ) {
        return { synced: 0, remaining: await queueCount() };
    }

    flushing = true;
    let synced = 0;

    try {
        const records = (await readAll()).sort((a, b) =>
            a.createdAt.localeCompare(b.createdAt),
        );

        for (const record of records) {
            const payload = await decryptPayload<QueuePayload>(record.payload);
            const response = await sendRecord(record, payload);

            if (!response.ok) {
                await updateAttempts(record);

                continue;
            }

            await deleteRecord(record.id);
            synced++;
        }
    } finally {
        flushing = false;
        dispatchQueueChanged();
    }

    return { synced, remaining: await queueCount() };
}

export async function queueCount(): Promise<number> {
    if (!offlineStorageAvailable()) {
        return 0;
    }

    const db = await openDatabase();

    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readonly');
        const request = transaction.objectStore(STORE_NAME).count();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export function onPortalOfflineQueueChanged(listener: () => void): () => void {
    window.addEventListener(QUEUE_CHANGED_EVENT, listener);

    return () => window.removeEventListener(QUEUE_CHANGED_EVENT, listener);
}

function offlineStorageAvailable(): boolean {
    return (
        typeof window !== 'undefined' &&
        'indexedDB' in window &&
        'crypto' in window &&
        typeof crypto.subtle !== 'undefined'
    );
}

async function queueRecord(
    kind: QueueKind,
    url: string,
    dedupeKey: string,
    plainPayload: QueuePayload,
): Promise<string> {
    if (!offlineStorageAvailable()) {
        throw new Error('Offline queue storage is unavailable.');
    }

    const existing = await findByDedupeKey(dedupeKey);

    if (existing) {
        return existing.id;
    }

    const record: QueueRecord = {
        id: crypto.randomUUID(),
        kind,
        url,
        dedupeKey,
        createdAt: new Date().toISOString(),
        attempts: 0,
        payload: await encryptPayload(plainPayload),
    };
    const db = await openDatabase();

    await new Promise<void>((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readwrite');
        transaction.objectStore(STORE_NAME).put(record);
        transaction.oncomplete = () => resolve();
        transaction.onerror = () => reject(transaction.error);
    });

    dispatchQueueChanged();
    void navigator.serviceWorker?.ready.then(registerBackgroundSync);

    return record.id;
}

function openDatabase(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);

        request.onupgradeneeded = () => {
            const db = request.result;

            if (!db.objectStoreNames.contains(STORE_NAME)) {
                const store = db.createObjectStore(STORE_NAME, {
                    keyPath: 'id',
                });
                store.createIndex('dedupeKey', 'dedupeKey', { unique: true });
                store.createIndex('createdAt', 'createdAt');
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function readAll(): Promise<QueueRecord[]> {
    const db = await openDatabase();

    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readonly');
        const request = transaction.objectStore(STORE_NAME).getAll();
        request.onsuccess = () => resolve(request.result as QueueRecord[]);
        request.onerror = () => reject(request.error);
    });
}

async function findByDedupeKey(dedupeKey: string): Promise<QueueRecord | null> {
    const db = await openDatabase();

    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readonly');
        const request = transaction
            .objectStore(STORE_NAME)
            .index('dedupeKey')
            .get(dedupeKey);
        request.onsuccess = () =>
            resolve((request.result as QueueRecord | undefined) ?? null);
        request.onerror = () => reject(request.error);
    });
}

async function deleteRecord(id: string): Promise<void> {
    const db = await openDatabase();

    await new Promise<void>((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readwrite');
        transaction.objectStore(STORE_NAME).delete(id);
        transaction.oncomplete = () => resolve();
        transaction.onerror = () => reject(transaction.error);
    });
}

async function updateAttempts(record: QueueRecord): Promise<void> {
    const db = await openDatabase();

    await new Promise<void>((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readwrite');
        transaction.objectStore(STORE_NAME).put({
            ...record,
            attempts: record.attempts + 1,
        });
        transaction.oncomplete = () => resolve();
        transaction.onerror = () => reject(transaction.error);
    });
}

async function sendRecord(
    record: QueueRecord,
    payload: QueuePayload,
): Promise<Response> {
    if (record.kind === 'questionnaire') {
        const body = (payload as QuestionnaireQueuePayload).body;

        return fetch(record.url, {
            method: 'POST',
            credentials: 'same-origin',
            redirect: 'follow',
            headers: {
                Accept: 'text/html, application/xhtml+xml',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Portal-Offline-Sync': '1',
            },
            body: JSON.stringify(body),
        });
    }

    const documentPayload = payload as DocumentUploadQueuePayload;
    const formData = new FormData();

    for (const [key, value] of Object.entries(documentPayload.fields)) {
        formData.append(key, value);
    }

    formData.append(
        'file',
        new File(
            [bytesToArrayBuffer(base64ToBytes(documentPayload.file.data))],
            documentPayload.file.name,
            {
                lastModified: documentPayload.file.lastModified,
                type: documentPayload.file.type || 'application/octet-stream',
            },
        ),
    );

    return fetch(record.url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Portal-Offline-Sync': '1',
        },
        body: formData,
    });
}

async function encryptPayload(
    payload: QueuePayload,
): Promise<EncryptedPayload> {
    const key = await offlineKey();
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const encoded = new TextEncoder().encode(JSON.stringify(payload));
    const encrypted = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        key,
        encoded,
    );

    return {
        iv: bytesToBase64(iv),
        data: bytesToBase64(new Uint8Array(encrypted)),
    };
}

async function decryptPayload<T>(payload: EncryptedPayload): Promise<T> {
    const key = await offlineKey();
    const decrypted = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: bytesToArrayBuffer(base64ToBytes(payload.iv)) },
        key,
        bytesToArrayBuffer(base64ToBytes(payload.data)),
    );

    return JSON.parse(new TextDecoder().decode(decrypted)) as T;
}

async function offlineKey(): Promise<CryptoKey> {
    const raw = storedOrNewKey();

    return crypto.subtle.importKey(
        'raw',
        bytesToArrayBuffer(raw),
        'AES-GCM',
        false,
        ['encrypt', 'decrypt'],
    );
}

function storedOrNewKey(): Uint8Array {
    const existing = localStorage.getItem(KEY_STORAGE);

    if (existing) {
        return base64ToBytes(existing);
    }

    const raw = crypto.getRandomValues(new Uint8Array(32));
    localStorage.setItem(KEY_STORAGE, bytesToBase64(raw));

    return raw;
}

async function stableHash(value: unknown): Promise<string> {
    const encoded = new TextEncoder().encode(JSON.stringify(value));
    const digest = await crypto.subtle.digest('SHA-256', encoded);

    return bytesToBase64(new Uint8Array(digest));
}

function stripLocalDocumentIds(
    body: Record<string, unknown>,
): Record<string, unknown> {
    const answers = body.answers;

    if (!answers || typeof answers !== 'object' || Array.isArray(answers)) {
        return body;
    }

    return {
        ...body,
        answers: Object.fromEntries(
            Object.entries(answers as Record<string, unknown>).map(
                ([questionId, answer]) => {
                    if (
                        !answer ||
                        typeof answer !== 'object' ||
                        Array.isArray(answer)
                    ) {
                        return [questionId, answer];
                    }

                    const answerRecord = answer as Record<string, unknown>;
                    const attached = Array.isArray(
                        answerRecord.attached_document_ids,
                    )
                        ? answerRecord.attached_document_ids
                              .map(String)
                              .filter(isUuid)
                        : [];

                    return [
                        questionId,
                        {
                            ...answerRecord,
                            attached_document_ids: attached,
                        },
                    ];
                },
            ),
        ),
    };
}

function fileToBase64(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            const result = String(reader.result ?? '');
            resolve(result.split(',')[1] ?? '');
        };
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(file);
    });
}

function bytesToBase64(bytes: Uint8Array): string {
    let binary = '';
    bytes.forEach((byte) => {
        binary += String.fromCharCode(byte);
    });

    return btoa(binary);
}

function base64ToBytes(value: string): Uint8Array {
    const binary = atob(value);
    const bytes = new Uint8Array(binary.length);

    for (let index = 0; index < binary.length; index++) {
        bytes[index] = binary.charCodeAt(index);
    }

    return bytes;
}

function bytesToArrayBuffer(bytes: Uint8Array): ArrayBuffer {
    return bytes.buffer.slice(
        bytes.byteOffset,
        bytes.byteOffset + bytes.byteLength,
    ) as ArrayBuffer;
}

function registerBackgroundSync(
    registration: ServiceWorkerRegistration,
): Promise<void> | undefined {
    return (registration as BackgroundSyncRegistration).sync?.register(
        'portal-offline-sync',
    );
}

function isUuid(value: string): boolean {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(
        value,
    );
}

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function dispatchQueueChanged(): void {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event(QUEUE_CHANGED_EVENT));
    }
}
