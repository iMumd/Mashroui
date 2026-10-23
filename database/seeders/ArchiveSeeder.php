<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ArchiveSeeder extends Seeder
{
    public function run(): void
    {
        // Archive completed projects and mark featured top ones
        $completed = Project::withoutGlobalScopes()->with('team')->where('status', 'completed')->get();
        foreach ($completed as $p) {
            if (! $p->archived_at) {
                $p->update(['archived_at' => $p->completed_at ? $p->completed_at->copy()->addDays(3) : now()->subDays(5)]);
            }
        }

        // Ensure featured projects
        $featured = Project::withoutGlobalScopes()->where('is_featured', true)->pluck('id')->all();
        if (count($featured) < 5) {
            $cands = Project::withoutGlobalScopes()->where('status', 'completed')->whereNotIn('id', $featured)->orderByDesc('id')->take(5 - count($featured))->pluck('id');
            foreach ($cands as $id) {
                Project::withoutGlobalScopes()->where('id', $id)->update(['is_featured' => true]);
            }
        }
    }
}
