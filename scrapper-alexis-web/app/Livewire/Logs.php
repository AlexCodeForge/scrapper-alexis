<?php

namespace App\Livewire;

use Livewire\Component;

class Logs extends Component
{
    public $selectedLog = 'facebook';
    public $selectedManualLog = null;
    public $lines = 100;
    public $autoRefresh = true;

    public function mount()
    {
        // Default to facebook logs
    }

    public function selectLog($log)
    {
        $this->selectedLog = $log;
        $this->selectedManualLog = null;
    }

    public function selectManualLog($filename)
    {
        $this->selectedLog = 'manual';
        $this->selectedManualLog = $filename;
    }

    public function downloadLog()
    {
        $logContent = '';

        if ($this->selectedLog === 'manual' && $this->selectedManualLog) {
            $logContent = getLogFileContent($this->selectedManualLog, 999999);
            $filename = $this->selectedManualLog;
        } else {
            $logContent = getJobLogs($this->selectedLog, 999999);
            $filename = "cron_{$this->selectedLog}_" . date('Y-m-d_H-i-s') . '.log';
        }

        return response()->streamDownload(function () use ($logContent) {
            echo $logContent;
        }, $filename, [
            'Content-Type' => 'text/plain',
        ]);
    }

    public function runCleanup()
    {
        \Log::info('Logs: Manual cleanup triggered');

        try {
            \Artisan::call('app:cleanup-downloaded-images');
            $output = \Artisan::output();

            session()->flash('success', 'Limpieza ejecutada correctamente. Revisa los logs para más detalles.');
            \Log::info('Logs: Manual cleanup completed', ['output' => $output]);
        } catch (\Exception $e) {
            session()->flash('error', 'Error al ejecutar la limpieza: ' . $e->getMessage());
            \Log::error('Logs: Manual cleanup failed', ['error' => $e->getMessage()]);
        }
    }

    public function render()
    {
        $logContent = '';

        if ($this->selectedLog === 'manual' && $this->selectedManualLog) {
            $logContent = getLogFileContent($this->selectedManualLog, $this->lines);
        } else {
            $logContent = getJobLogs($this->selectedLog, $this->lines);
        }

        $manualLogs = getManualLogFiles();

        // Get cron job status
        $facebookEnabled = getJobStatus('facebook');
        $imageGeneratorEnabled = getJobStatus('image-generator');
        $pagePosterEnabled = getJobStatus('page-poster');

        // Get cleanup settings
        $cleanupSettings = \App\Models\PostingSetting::getSettings();
        $cleanupEnabled = $cleanupSettings->auto_cleanup_enabled;

        return view('livewire.logs', [
            'logContent' => $logContent,
            'manualLogs' => $manualLogs,
            'facebookEnabled' => $facebookEnabled,
            'imageGeneratorEnabled' => $imageGeneratorEnabled,
            'pagePosterEnabled' => $pagePosterEnabled,
            'cleanupEnabled' => $cleanupEnabled,
            'cleanupDays' => $cleanupSettings->cleanup_days,
            'lastCleanupAt' => $cleanupSettings->last_cleanup_at,
        ])->layout('components.layouts.app', ['title' => 'Logs']);
    }
}

