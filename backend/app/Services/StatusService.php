<?php

namespace App\Services;

use App\Models\Status;
use App\Models\StatusHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class StatusService
{
    public function resolveStatusId(string $module, string $code): int
    {
        $id = Status::query()
            ->where('module', $module)
            ->where('code', $code)
            ->value('id');

        if (! $id) {
            throw ValidationException::withMessages([
                'status_id' => "Status {$module}:{$code} not found.",
            ]);
        }

        return (int) $id;
    }

    public function validateStatusForModule(int $statusId, string $module): Status
    {
        $status = Status::query()->find($statusId);

        if (! $status) {
            throw ValidationException::withMessages([
                'status_id' => 'Status not found.',
            ]);
        }

        if ($status->module !== $module) {
            throw ValidationException::withMessages([
                'status_id' => "Status {$status->code} does not belong to module {$module}.",
            ]);
        }

        return $status;
    }

    public function transition(
        Model $model,
        string $module,
        int $toStatusId,
        ?int $changedBy = null,
        ?string $reason = null,
        ?array $meta = null
    ): void {
        $toStatus = $this->validateStatusForModule($toStatusId, $module);

        $fromStatusId = $model->status_id ?? null;
        if ((int) $fromStatusId === (int) $toStatus->id) {
            return;
        }

        if ($fromStatusId) {
            $this->validateStatusForModule((int) $fromStatusId, $module);
        }

        $model->status_id = $toStatus->id;
        $model->save();

        StatusHistory::create([
            'module' => $module,
            'entity_type' => $model->getMorphClass(),
            'entity_id' => $model->getKey(),
            'from_status_id' => $fromStatusId,
            'to_status_id' => $toStatus->id,
            'changed_by' => $changedBy,
            'reason' => $reason,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
