import { router } from '@inertiajs/react';
import {
    CheckCircle2,
    FileText,
    ListChecks,
    RotateCcw,
    ShieldAlert,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { RollupPanel } from './client-detail-knowledge';
import {
    AnalysisReadinessIndicator,
    Metric,
    WebsiteAuditSignal,
    analysisRunButtonClass,
    formatDate,
    formatLabel,
    standardAdvisoryStatusVariant,
} from './client-detail-presenters';
import type {
    StandardAdvisoryGeneratePayload,
    StandardAdvisorySummary,
} from './client-detail-types';
export function StandardAdvisoryPanel({
    summary,
    onRunAnalysis,
    onGeneratePack,
    generatingPack,
}: {
    summary: StandardAdvisorySummary;
    onRunAnalysis: () => void;
    onGeneratePack: (payload?: StandardAdvisoryGeneratePayload) => void;
    generatingPack: boolean;
}) {
    const [waiverReason, setWaiverReason] = useState('');
    const [websiteUrl, setWebsiteUrl] = useState(
        summary.website_audit.confirmed_url ??
            summary.website_audit.candidates[0]?.url ??
            '',
    );
    const clientReport = summary.reports.client;
    const pendingWebsiteCandidate = summary.website_audit.candidates.find(
        (candidate) => candidate.source === 'client',
    );
    const waivableModules = summary.analysis_modules.filter(
        (module) => module.waivable,
    );
    const releaseClientReport = () => {
        if (
            !clientReport ||
            clientReport.review_status !== 'pending_review' ||
            !clientReport.release_url
        ) {
            return;
        }

        router.patch(clientReport.release_url, {}, { preserveScroll: true });
    };
    const generateWithWaiver = () => {
        const reason = waiverReason.trim();

        if (reason === '' || waivableModules.length === 0) {
            toast.error(
                'Add a waiver reason before generating a partial pack.',
            );

            return;
        }

        onGeneratePack({
            waiver_reason: reason,
            waiver_modules: waivableModules.map((module) => module.module),
        });
    };
    const confirmWebsiteUrl = () => {
        const url = websiteUrl.trim();

        if (url === '') {
            toast.error('Enter the website URL before confirming it.');

            return;
        }

        router.post(
            summary.website_audit.confirm_url,
            {
                url,
                source_answer_ids: summary.website_audit.candidates
                    .filter((candidate) => candidate.url === url)
                    .map((candidate) => candidate.answer_id)
                    .filter(
                        (answerId): answerId is string => answerId !== null,
                    ),
            },
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(
                        errors.url ?? 'The website URL could not be confirmed.',
                    ),
            },
        );
    };

    return (
        <section
            id="section-standard-advisory"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <ListChecks className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Standard Advisory workflow
                    </h2>
                    <Badge
                        variant={standardAdvisoryStatusVariant(summary.status)}
                    >
                        {summary.status_label}
                    </Badge>
                </div>
                <div className="flex flex-wrap gap-2">
                    <AnalysisReadinessIndicator
                        readiness={summary.analysis_readiness}
                    />
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className={analysisRunButtonClass(
                                        summary.analysis_readiness.level,
                                    )}
                                    disabled={!summary.can_run_analysis}
                                    onClick={onRunAnalysis}
                                >
                                    <RotateCcw
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Run analysis
                                </Button>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" className="max-w-xs">
                            Runs every Standard Advisory analysis module,
                            including the website review, then refreshes the
                            business health radar.
                        </TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span>
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={
                                        !summary.can_generate_pack ||
                                        generatingPack
                                    }
                                    onClick={() => onGeneratePack()}
                                >
                                    <FileText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {generatingPack
                                        ? 'Generating...'
                                        : 'Generate pack'}
                                </Button>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" className="max-w-xs">
                            Creates advisor, client, stakeholder, and trajectory
                            reports from the latest analysis.
                        </TooltipContent>
                    </Tooltip>
                    {clientReport?.review_status === 'pending_review' && (
                        <>
                            {(clientReport.view_url ??
                                clientReport.download_url) && (
                                <Button asChild size="sm" variant="outline">
                                    <a
                                        href={
                                            clientReport.view_url ??
                                            clientReport.download_url ??
                                            ''
                                        }
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <FileText
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Review client report
                                    </a>
                                </Button>
                            )}
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={!clientReport.release_url}
                                onClick={releaseClientReport}
                            >
                                <CheckCircle2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Release client report
                            </Button>
                        </>
                    )}
                </div>
            </div>

            <p className="text-sm text-muted-foreground">
                {summary.next_action}
            </p>

            <RollupPanel
                title="Client momentum"
                description={summary.momentum.next_action}
                meta={
                    <Badge variant="outline">
                        {summary.momentum.completed}/{summary.momentum.total}{' '}
                        complete
                    </Badge>
                }
                className="bg-muted/20"
            >
                <div className="h-2 overflow-hidden rounded-full bg-muted">
                    <div
                        className="h-full rounded-full bg-emerald-500"
                        style={{ width: `${summary.momentum.percent}%` }}
                    />
                </div>
                <div className="grid gap-2 md:grid-cols-2">
                    {summary.momentum.items.map((item) => (
                        <div
                            key={item.key}
                            className="flex items-start justify-between gap-3 rounded-md border bg-background p-3"
                        >
                            <div className="min-w-0">
                                <div className="text-sm font-medium">
                                    {item.label}
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {item.description}
                                </div>
                            </div>
                            <Badge
                                variant={
                                    item.status === 'complete'
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {item.status === 'waiting_advisor'
                                    ? 'Advisor'
                                    : item.status === 'not_required'
                                      ? 'Not required'
                                      : formatLabel(item.status)}
                            </Badge>
                        </div>
                    ))}
                </div>
            </RollupPanel>

            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <Metric
                    label="Questionnaire"
                    value={
                        summary.questionnaire_submitted
                            ? `Submitted ${formatDate(summary.questionnaire_submitted_at)}`
                            : 'Not submitted'
                    }
                />
                <Metric
                    label="Evidence"
                    value={`${summary.document_count} uploaded / ${summary.verified_document_count} verified`}
                />
                <Metric
                    label="Analysis"
                    value={
                        summary.analysis_waived > 0
                            ? `${summary.analysis_completed}/${summary.analysis_total} complete, ${summary.analysis_waived} waived`
                            : `${summary.analysis_completed}/${summary.analysis_total} modules complete`
                    }
                />
                <Metric
                    label="Client report"
                    value={
                        clientReport
                            ? formatLabel(clientReport.review_status)
                            : 'Not generated'
                    }
                />
            </div>

            <RollupPanel
                title="Website audit readiness"
                description={summary.website_audit.next_action}
                defaultOpen={['missing_url', 'awaiting_confirmation'].includes(
                    summary.website_audit.status,
                )}
                meta={
                    <Badge
                        variant={
                            summary.website_audit.status === 'ready'
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {summary.website_audit.status_label}
                    </Badge>
                }
                className="bg-muted/20"
            >
                {pendingWebsiteCandidate ? (
                    <p className="text-sm text-muted-foreground">
                        Client-submitted URL awaiting confirmation:{' '}
                        <span className="font-medium text-foreground">
                            {pendingWebsiteCandidate.url}
                        </span>
                    </p>
                ) : null}
                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <WebsiteAuditSignal
                        label="URL"
                        complete={summary.website_audit.has_url}
                    />
                    <WebsiteAuditSignal
                        label="Page evidence"
                        complete={
                            summary.website_audit.has_website_page_evidence
                        }
                    />
                    <WebsiteAuditSignal
                        label="Offer evidence"
                        complete={
                            summary.website_audit.has_product_service_evidence
                        }
                    />
                    <WebsiteAuditSignal
                        label="SEO evidence"
                        complete={summary.website_audit.has_seo_evidence}
                    />
                </div>
                <div className="grid gap-2 md:grid-cols-[minmax(0,1fr)_auto]">
                    <div className="grid gap-1.5">
                        <Label htmlFor="website_audit_confirmed_url">
                            Public website URL
                        </Label>
                        <Input
                            id="website_audit_confirmed_url"
                            type="url"
                            value={websiteUrl}
                            onChange={(event) =>
                                setWebsiteUrl(event.target.value)
                            }
                            maxLength={2048}
                            placeholder="https://example.co.nz"
                        />
                    </div>
                    <div className="flex items-end">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={websiteUrl.trim() === ''}
                            onClick={confirmWebsiteUrl}
                        >
                            <CheckCircle2
                                className="size-4"
                                aria-hidden="true"
                            />
                            Confirm URL
                        </Button>
                    </div>
                </div>
            </RollupPanel>

            {summary.missing.length > 0 ? (
                <RollupPanel
                    title="Readiness gaps"
                    description={`${summary.missing.length} item${summary.missing.length === 1 ? '' : 's'} need attention.`}
                    defaultOpen={!summary.can_run_analysis}
                    className="bg-muted/30"
                >
                    <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        {summary.missing.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                </RollupPanel>
            ) : (
                <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                    Standard Advisory workflow is ready for the client
                    conversation.
                </div>
            )}

            {summary.warnings.length > 0 && (
                <div className="rounded-md border border-amber-300 bg-amber-50 p-3">
                    <div className="text-sm font-medium text-amber-950">
                        Advisor warnings
                    </div>
                    <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-900">
                        {summary.warnings.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                </div>
            )}

            {summary.can_record_pack_waiver && waivableModules.length > 0 && (
                <div className="rounded-md border border-amber-300 bg-amber-50 p-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2 text-sm font-medium text-amber-950">
                            <ShieldAlert
                                className="size-4"
                                aria-hidden="true"
                            />
                            Partial pack waiver
                        </div>
                        <Badge variant="outline">
                            {waivableModules.length} module
                            {waivableModules.length === 1 ? '' : 's'}
                        </Badge>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {waivableModules.map((module) => (
                            <Badge key={module.module} variant="outline">
                                {module.label} · {formatLabel(module.status)}
                            </Badge>
                        ))}
                    </div>
                    <div className="mt-3 grid gap-2">
                        <Label htmlFor="standard_advisory_waiver_reason">
                            Advisor waiver reason
                        </Label>
                        <textarea
                            id="standard_advisory_waiver_reason"
                            value={waiverReason}
                            onChange={(event) =>
                                setWaiverReason(event.target.value)
                            }
                            className="min-h-20 rounded-md border bg-background px-3 py-2 text-sm"
                            maxLength={1200}
                        />
                        <div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={
                                    generatingPack || waiverReason.trim() === ''
                                }
                                onClick={generateWithWaiver}
                            >
                                <ShieldAlert
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Generate with waiver
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {summary.pack_waivers.length > 0 && (
                <RollupPanel
                    title="Recorded pack waivers"
                    description={`${summary.pack_waivers.length} recorded waiver${summary.pack_waivers.length === 1 ? '' : 's'}.`}
                    className="bg-muted/20"
                >
                    <div className="grid gap-2">
                        {summary.pack_waivers.map((waiver) => (
                            <div
                                key={waiver.id}
                                className="rounded border bg-background p-2 text-sm"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="font-medium">
                                        {waiver.modules
                                            .map((module) =>
                                                formatLabel(module),
                                            )
                                            .join(', ')}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {formatDate(waiver.waived_at)}
                                    </span>
                                </div>
                                <p className="mt-1 text-muted-foreground">
                                    {waiver.reason}
                                </p>
                            </div>
                        ))}
                    </div>
                </RollupPanel>
            )}

            <RollupPanel
                title="Workflow details"
                description="Analysis module and report pack status."
            >
                <div className="grid gap-4 lg:grid-cols-2">
                    <div>
                        <div className="text-sm font-medium">
                            Analysis modules
                        </div>
                        <div className="mt-3 grid gap-2">
                            {summary.analysis_modules.map((module) => (
                                <div
                                    key={module.module}
                                    className="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span>{module.label}</span>
                                    <Badge
                                        variant={
                                            module.ready_for_pack
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {module.waived
                                            ? 'Waived'
                                            : module.completed
                                              ? 'Completed'
                                              : formatLabel(module.status)}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    </div>
                    <div>
                        <div className="text-sm font-medium">Report pack</div>
                        <div className="mt-3 grid gap-2">
                            {Object.entries(summary.reports).map(
                                ([key, report]) => (
                                    <div
                                        key={key}
                                        className="flex items-center justify-between gap-3 text-sm"
                                    >
                                        <span>{formatLabel(key)}</span>
                                        <div className="flex items-center gap-2">
                                            <Badge variant="outline">
                                                {report
                                                    ? formatLabel(
                                                          report.review_status,
                                                      )
                                                    : 'Not generated'}
                                            </Badge>
                                            {(report?.view_url ??
                                                report?.download_url) && (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-7 px-2"
                                                >
                                                    <a
                                                        href={
                                                            report.view_url ??
                                                            report.download_url ??
                                                            ''
                                                        }
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        aria-label={`View ${formatLabel(key)} report PDF`}
                                                    >
                                                        <FileText
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        View
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                    </div>
                </div>
            </RollupPanel>
        </section>
    );
}
