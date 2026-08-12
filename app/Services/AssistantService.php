<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Scopes\TermScope;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AssistantService
{
    public function chat(array $history, string $message): string
    {
        $messages = [];

        if ($context = $this->buildProjectsContext()) {
            $messages[] = ['role' => 'user', 'content' => $context];
        }

        foreach ($history as $item) {
            $messages[] = ['role' => $item['role'], 'content' => $item['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $response = Http::withHeaders(['ngrok-skip-browser-warning' => 'true'])
            ->timeout(60)
            ->post(rtrim(config('services.fikra.base_url'), '/').'/api/chat', [
                'model' => config('services.fikra.model'),
                'messages' => $messages,
                'stream' => false,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('fikra_unreachable');
        }

        $reply = $response->json('message.content');

        if (! is_string($reply) || trim($reply) === '') {
            throw new RuntimeException('fikra_empty_reply');
        }

        return $reply;
    }

    private function buildProjectsContext(): ?string
    {
        $projects = Project::withoutGlobalScope(TermScope::class)
            ->with(['department:id,name', 'specialization:id,name', 'proposal:id,project_id,description,problems,solutions,features_value'])
            ->select(['id', 'name', 'description', 'department_id', 'specialization_id', 'status'])
            ->latest('id')
            ->limit(200)
            ->get();

        if ($projects->isEmpty()) {
            return null;
        }

        $lines = ['[PROJECTS_CONTEXT]'];

        foreach ($projects as $project) {
            $lines[] = sprintf(
                '- المشروع: "%s" | القسم: %s | التخصص: %s | الحالة: %s',
                $project->name,
                $project->department?->name ?? 'غير محدد',
                $project->specialization?->name ?? 'غير محدد',
                $project->status->value,
            );
            $lines[] = '  الوصف: '.($project->proposal?->description ?? $project->description ?? 'لا يوجد وصف');
            $lines[] = sprintf(
                '  المشكلة: %s | الحل: %s | القيمة المضافة: %s',
                $project->proposal?->problems ?? 'غير متوفر',
                $project->proposal?->solutions ?? 'غير متوفر',
                $project->proposal?->features_value ?? 'غير متوفر',
            );
        }

        $lines[] = '[/PROJECTS_CONTEXT]';

        return implode("\n", $lines);
    }
}
