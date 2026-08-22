<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Scopes\TermScope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
            ->timeout(55)
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

    /** سياق مختصر جداً (اسم + قسم + حالة فقط) — نموذج CPU صغير يبطّئ كثيراً مع نص طويل بكل رسالة */
    private function buildProjectsContext(): ?string
    {
        $projects = Project::withoutGlobalScope(TermScope::class)
            ->with(['department:id,name'])
            ->select(['id', 'name', 'department_id', 'status'])
            ->latest('id')
            ->limit(15)
            ->get();

        if ($projects->isEmpty()) {
            return null;
        }

        $lines = ['[PROJECTS_CONTEXT] عناوين مشاريع تخرج سابقة/حالية على المنصة، للرجوع إليها فقط لو سُئلت عن تشابه فكرة:'];

        foreach ($projects as $project) {
            $lines[] = sprintf(
                '- %s (%s، %s)',
                Str::limit($project->name, 70),
                $project->department?->name ?? 'غير محدد',
                $project->status->value,
            );
        }

        $lines[] = '[/PROJECTS_CONTEXT]';

        return implode("\n", $lines);
    }
}
