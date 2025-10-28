<?php

namespace App\Livewire;

use Livewire\Component;

class Logs extends Component
{
    public $selectedLog = 'facebook';
    public $selectedManualLog = null;
    public $lines = 100;
    public $autoRefresh = false;

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
        $twitterEnabled = getJobStatus('twitter');

        return view('livewire.logs', [
            'logContent' => $logContent,
            'manualLogs' => $manualLogs,
            'facebookEnabled' => $facebookEnabled,
            'twitterEnabled' => $twitterEnabled,
        ])->layout('components.layouts.app', ['title' => 'Logs']);
    }
}

