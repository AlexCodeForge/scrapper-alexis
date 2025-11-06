<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerState extends Model
{
    protected $table = 'scheduler_state';
    
    protected $fillable = [
        'job_name',
        'last_run_at',
        'next_interval_minutes',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
        'next_interval_minutes' => 'integer',
    ];

    /**
     * Get or create scheduler state for a job
     */
    public static function getForJob(string $jobName): self
    {
        return self::firstOrCreate(
            ['job_name' => $jobName],
            [
                'last_run_at' => null,
                'next_interval_minutes' => null,
            ]
        );
    }

    /**
     * Update state after job runs
     */
    public function markAsRun(int $nextInterval): void
    {
        $this->update([
            'last_run_at' => now(),
            'next_interval_minutes' => $nextInterval,
        ]);
    }
}
