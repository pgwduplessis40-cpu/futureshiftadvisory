<?php

declare(strict_types=1);

namespace App\Http\Controllers\MobileApi;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use BackedEnum;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ClientController extends Controller
{
    public function index(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        return [
            'clients' => Client::query()
                ->whereIn('id', $user->accessibleClientIds())
                ->orderBy('legal_name')
                ->get()
                ->map(fn (Client $client): array => $this->payload($client))
                ->all(),
        ];
    }

    public function show(Request $request, string $client): array
    {
        /** @var User $user */
        $user = $request->user();
        $model = Client::query()->whereKey($client)->first();

        if (! $model instanceof Client || ! in_array((string) $model->getKey(), $user->accessibleClientIds(), true)) {
            throw new NotFoundHttpException;
        }

        return ['client' => $this->payload($model)];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Client $client): array
    {
        $engagementType = $client->engagement_type;
        $status = $client->status;

        return [
            'id' => (string) $client->getKey(),
            'legal_name' => $client->legal_name,
            'engagement_type' => $engagementType instanceof BackedEnum ? $engagementType->value : $engagementType,
            'status' => $status instanceof BackedEnum ? $status->value : $status,
            'data_quality' => $client->data_quality,
        ];
    }
}
