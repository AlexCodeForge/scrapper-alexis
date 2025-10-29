<?php

namespace App\Livewire;

use App\Services\PostingService;
use Livewire\Component;
use Livewire\WithFileUploads;

class Settings extends Component
{
    use WithFileUploads;

    public $facebookIntervalMin = 40;
    public $facebookIntervalMax = 80;
    public $twitterIntervalMin = 1;
    public $twitterIntervalMax = 60;
    public $facebookEnabled = true;
    public $twitterEnabled = true;
    public $facebookEmail = '';
    public $facebookPassword = '';
    public $facebookProfiles = '';
    public $twitterEmail = '';
    public $twitterPassword = '';
    public $twitterDisplayName = '';
    public $twitterUsername = '';
    public $twitterAvatarUrl = '';
    public $proxyServer = '';
    public $proxyUsername = '';
    public $proxyPassword = '';

    // Facebook Page Posting Settings
    public $pageName = '';
    public $pageIntervalMin = 60;
    public $pageIntervalMax = 120;
    public $pagePostingEnabled = false;
    public $autoCleanupEnabled = false;
    public $cleanupDays = 7;

    // File uploads for auth
    public $twitterAuthFile;
    public $facebookAuthFile;

    // Track if auth files exist
    public $twitterAuthExists = false;
    public $facebookAuthExists = false;

    public function mount()
    {
        $this->loadSettings();
        $this->loadPagePostingSettings();
        $this->checkAuthFiles();
    }

    public function loadPagePostingSettings()
    {
        $postingService = app(PostingService::class);
        $settings = $postingService->getSettings();

        $this->pageName = $settings->page_name ?? '';
        $this->pageIntervalMin = $settings->interval_min;
        $this->pageIntervalMax = $settings->interval_max;
        $this->pagePostingEnabled = $settings->enabled;
        $this->autoCleanupEnabled = $settings->auto_cleanup_enabled ?? false;
        $this->cleanupDays = $settings->cleanup_days ?? 7;
    }

    public function checkAuthFiles()
    {
        $this->twitterAuthExists = file_exists('/app/auth/auth_x.json');
        $this->facebookAuthExists = file_exists('/app/auth/auth_facebook.json');
    }

    // Bugfix: Explicit toggle methods (Livewire $toggle magic doesn't trigger updated* hooks)
    public function toggleFacebook()
    {
        $this->facebookEnabled = !$this->facebookEnabled;

        if (!updateEnvFile('FACEBOOK_SCRAPER_ENABLED', $this->facebookEnabled ? 'true' : 'false')) {
            session()->flash('error', 'Error: No se pudo actualizar el estado del scraper de Facebook. Verifique los permisos del archivo .env.');
            $this->facebookEnabled = !$this->facebookEnabled; // Revert
            return;
        }

        updateCrontab(
            $this->facebookIntervalMin,
            $this->twitterIntervalMin,
            $this->facebookEnabled,
            $this->twitterEnabled
        );

        session()->flash('success', '✓ Facebook scraper ' . ($this->facebookEnabled ? 'activado' : 'desactivado'));
    }

    public function toggleTwitter()
    {
        error_log("toggleTwitter: METHOD CALLED - START");
        $this->twitterEnabled = !$this->twitterEnabled;
        error_log("toggleTwitter: twitterEnabled toggled to " . ($this->twitterEnabled ? 'true' : 'false'));

        $result = updateEnvFile('TWITTER_POSTER_ENABLED', $this->twitterEnabled ? 'true' : 'false');
        error_log("toggleTwitter: updateEnvFile returned: " . ($result ? 'true' : 'false'));

        if (!$result) {
            error_log("toggleTwitter: Write FAILED - reverting state");
            session()->flash('error', 'Error: No se pudo actualizar el estado del publicador de Twitter. Verifique los permisos del archivo .env.');
            $this->twitterEnabled = !$this->twitterEnabled; // Revert
            return;
        }

        error_log("toggleTwitter: Calling updateCrontab");
        updateCrontab(
            $this->facebookIntervalMin,
            $this->twitterIntervalMin,
            $this->facebookEnabled,
            $this->twitterEnabled
        );

        error_log("toggleTwitter: SUCCESS - method complete");
        session()->flash('success', '✓ Twitter poster ' . ($this->twitterEnabled ? 'activado' : 'desactivado'));
    }

    public function loadSettings()
    {
        // In Docker, scraper folder is mounted at /scraper
        $envPath = '/scraper/.env';

        if (file_exists($envPath)) {
            $envContent = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($envContent as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    switch ($key) {
                        case 'FACEBOOK_INTERVAL_MIN':
                            $this->facebookIntervalMin = (int) $value;
                            break;
                        case 'FACEBOOK_INTERVAL_MAX':
                            $this->facebookIntervalMax = (int) $value;
                            break;
                        case 'TWITTER_INTERVAL_MIN':
                            $this->twitterIntervalMin = (int) $value;
                            break;
                        case 'TWITTER_INTERVAL_MAX':
                            $this->twitterIntervalMax = (int) $value;
                            break;
                        case 'FACEBOOK_SCRAPER_ENABLED':
                            $this->facebookEnabled = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                            break;
                        case 'TWITTER_POSTER_ENABLED':
                            $this->twitterEnabled = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                            break;
                        case 'FACEBOOK_EMAIL':
                            $this->facebookEmail = $value;
                            break;
                        case 'FACEBOOK_PASSWORD':
                            $this->facebookPassword = $value;
                            break;
                        case 'FACEBOOK_PROFILES':
                            $this->facebookProfiles = str_replace(',', "\n", $value);
                            break;
                        case 'X_EMAIL':
                            $this->twitterEmail = $value;
                            break;
                        case 'X_PASSWORD':
                            $this->twitterPassword = $value;
                            break;
                        case 'X_DISPLAY_NAME':
                            $this->twitterDisplayName = $value;
                            break;
                        case 'X_USERNAME':
                            $this->twitterUsername = $value;
                            break;
                        case 'X_AVATAR_URL':
                            $this->twitterAvatarUrl = $value;
                            break;
                        case 'PROXY_SERVER':
                            $this->proxyServer = $value;
                            break;
                        case 'PROXY_USERNAME':
                            $this->proxyUsername = $value;
                            break;
                        case 'PROXY_PASSWORD':
                            $this->proxyPassword = $value;
                            break;
                    }
                }
            }
        }
    }

    public function saveSettings()
    {
        $this->validate([
            'facebookIntervalMin' => 'required|integer|min:1|max:1440',
            'facebookIntervalMax' => 'required|integer|min:1|max:1440|gte:facebookIntervalMin',
            'twitterIntervalMin' => 'required|integer|min:1|max:1440',
            'twitterIntervalMax' => 'required|integer|min:1|max:1440|gte:twitterIntervalMin',
            'facebookEmail' => 'required|string',
            'twitterEmail' => 'required|string',
            'proxyServer' => 'required',
        ]);

        // Update cron intervals and enabled status
        $cronUpdated = updateCrontab(
            $this->facebookIntervalMin,
            $this->twitterIntervalMin,
            $this->facebookEnabled,
            $this->twitterEnabled
        );

        // Update environment variables
        $profilesCommaSeparated = str_replace("\n", ',', trim($this->facebookProfiles));

        $updates = [
            'FACEBOOK_INTERVAL_MIN' => $this->facebookIntervalMin,
            'FACEBOOK_INTERVAL_MAX' => $this->facebookIntervalMax,
            'TWITTER_INTERVAL_MIN' => $this->twitterIntervalMin,
            'TWITTER_INTERVAL_MAX' => $this->twitterIntervalMax,
            'FACEBOOK_SCRAPER_ENABLED' => $this->facebookEnabled ? 'true' : 'false',
            'TWITTER_POSTER_ENABLED' => $this->twitterEnabled ? 'true' : 'false',
            'FACEBOOK_EMAIL' => $this->facebookEmail,
            'FACEBOOK_PASSWORD' => $this->facebookPassword,
            'FACEBOOK_PROFILES' => $profilesCommaSeparated,
            'X_EMAIL' => $this->twitterEmail,
            'X_PASSWORD' => $this->twitterPassword,
            'X_DISPLAY_NAME' => $this->twitterDisplayName,
            'X_USERNAME' => $this->twitterUsername,
            'X_AVATAR_URL' => $this->twitterAvatarUrl,
            'PROXY_SERVER' => $this->proxyServer,
            'PROXY_USERNAME' => $this->proxyUsername,
            'PROXY_PASSWORD' => $this->proxyPassword,
        ];

        // Bugfix: Check for file write failures and report them to the user
        $failedUpdates = [];
        foreach ($updates as $key => $value) {
            if (!updateEnvFile($key, $value)) {
                $failedUpdates[] = $key;
                \Log::error("Settings: Failed to update env key", ['key' => $key, 'value' => substr($value, 0, 50)]);
            }
        }

        if (!empty($failedUpdates)) {
            session()->flash('error', 'Error al guardar la configuración. No se pudieron actualizar: ' . implode(', ', $failedUpdates) . '. Verifique los permisos del archivo .env.');
        } elseif ($cronUpdated) {
            session()->flash('success', 'Configuración guardada y programación de cron actualizada exitosamente.');
        } else {
            session()->flash('warning', 'Configuración guardada pero la actualización de cron falló. Es posible que deba actualizar crontab manualmente.');
        }
    }

    public function uploadTwitterAuth()
    {
        \Log::info("uploadTwitterAuth: Method called");

        $this->validate([
            'twitterAuthFile' => 'required|file|mimes:json|max:2048',
        ]);

        try {
            $authPath = '/app/auth/auth_x.json';

            // Save uploaded file as auth_x.json
            $this->twitterAuthFile->storeAs('', 'auth_x.json', ['disk' => 'scraper_auth']);

            // Bugfix: Ensure www-data owns the file so it can be deleted later
            chown($authPath, 'www-data');
            chgrp($authPath, 'www-data');

            \Log::info("uploadTwitterAuth: File saved", ['auth' => $authPath]);

            // Create empty session file (will be populated on first run)
            $sessionPath = '/app/auth/auth_x_session.json';
            file_put_contents($sessionPath, json_encode([
                'username' => '',
                'display_name' => '',
                'avatar_url' => '',
                'login_time' => time(),
                'success' => true
            ], JSON_PRETTY_PRINT));

            // Bugfix: Ensure www-data owns the session file
            chown($sessionPath, 'www-data');
            chgrp($sessionPath, 'www-data');

            \Log::info("uploadTwitterAuth: Session file created", ['session' => $sessionPath]);

            // Clear uploaded file property
            $this->reset(['twitterAuthFile']);

            // Update status
            $this->checkAuthFiles();

            session()->flash('success', '✅ Archivo de autenticación de Twitter subido correctamente! Ejecuta el Twitter Poster para completar la configuración del perfil.');

        } catch (\Exception $e) {
            \Log::error("uploadTwitterAuth: Failed", ['error' => $e->getMessage()]);
            session()->flash('error', 'Error al subir archivo de autenticación: ' . $e->getMessage());
        }
    }

    public function deleteTwitterAuth()
    {
        \Log::info("deleteTwitterAuth: Method called");

        try {
            $authPath = '/app/auth/auth_x.json';
            $sessionPath = '/app/auth/auth_x_session.json';

            // Delete files
            if (file_exists($authPath)) {
                unlink($authPath);
            }
            if (file_exists($sessionPath)) {
                unlink($sessionPath);
            }

            \Log::info("deleteTwitterAuth: Files deleted");

            // Update status
            $this->checkAuthFiles();

            session()->flash('success', '✅ Autenticación de Twitter eliminada correctamente.');

        } catch (\Exception $e) {
            \Log::error("deleteTwitterAuth: Failed", ['error' => $e->getMessage()]);
            session()->flash('error', 'Error al eliminar autenticación: ' . $e->getMessage());
        }
    }

    public function uploadFacebookAuth()
    {
        \Log::info("uploadFacebookAuth: Method called");

        $this->validate([
            'facebookAuthFile' => 'required|file|mimes:json|max:2048',
        ]);

        try {
            $authPath = '/app/auth/auth_facebook.json';

            // Save uploaded file
            $this->facebookAuthFile->storeAs('', 'auth_facebook.json', ['disk' => 'scraper_auth']);

            // Bugfix: Ensure www-data owns the file so it can be deleted later
            chown($authPath, 'www-data');
            chgrp($authPath, 'www-data');

            \Log::info("uploadFacebookAuth: File saved", ['auth' => $authPath]);

            // Create empty session file (will be populated on first run)
            $sessionPath = '/app/auth/auth_facebook_session.json';
            file_put_contents($sessionPath, json_encode([
                'session_storage' => []
            ], JSON_PRETTY_PRINT));

            // Bugfix: Ensure www-data owns the session file
            chown($sessionPath, 'www-data');
            chgrp($sessionPath, 'www-data');

            \Log::info("uploadFacebookAuth: Session file created", ['session' => $sessionPath]);

            // Clear uploaded file property
            $this->reset(['facebookAuthFile']);

            // Update status
            $this->checkAuthFiles();

            session()->flash('success', '✅ Archivo de autenticación de Facebook subido correctamente! El scraper usará esta sesión automáticamente.');

        } catch (\Exception $e) {
            \Log::error("uploadFacebookAuth: Failed", ['error' => $e->getMessage()]);
            session()->flash('error', 'Error al subir archivo de autenticación: ' . $e->getMessage());
        }
    }

    public function deleteFacebookAuth()
    {
        \Log::info("deleteFacebookAuth: Method called");

        try {
            $authPath = '/app/auth/auth_facebook.json';
            $sessionPath = '/app/auth/auth_facebook_session.json';

            // Delete files
            if (file_exists($authPath)) {
                unlink($authPath);
            }
            if (file_exists($sessionPath)) {
                unlink($sessionPath);
            }

            \Log::info("deleteFacebookAuth: Files deleted");

            // Update status
            $this->checkAuthFiles();

            session()->flash('success', '✅ Autenticación de Facebook eliminada correctamente.');

        } catch (\Exception $e) {
            \Log::error("deleteFacebookAuth: Failed", ['error' => $e->getMessage()]);
            session()->flash('error', 'Error al eliminar autenticación: ' . $e->getMessage());
        }
    }

    public function savePagePostingSettings()
    {
        $this->validate([
            'pageName' => 'required|string|max:255',
            'pageIntervalMin' => 'required|integer|min:10|max:1440',
            'pageIntervalMax' => 'required|integer|min:10|max:1440|gte:pageIntervalMin',
            'cleanupDays' => 'required|integer|min:1|max:365',
        ]);

        $postingService = app(PostingService::class);

        $result = $postingService->updateSettings([
            'page_name' => $this->pageName,
            'interval_min' => $this->pageIntervalMin,
            'interval_max' => $this->pageIntervalMax,
            'enabled' => $this->pagePostingEnabled,
            'auto_cleanup_enabled' => $this->autoCleanupEnabled,
            'cleanup_days' => $this->cleanupDays,
        ]);

        if ($result) {
            session()->flash('success', 'Configuración de publicación en página guardada correctamente.');
        } else {
            session()->flash('error', 'Error al guardar la configuración de publicación en página.');
        }
    }

    public function togglePagePosting()
    {
        $this->pagePostingEnabled = !$this->pagePostingEnabled;

        $postingService = app(PostingService::class);

        $result = $postingService->updateSettings([
            'enabled' => $this->pagePostingEnabled,
        ]);

        if ($result) {
            session()->flash('success', '✓ Publicación en página ' . ($this->pagePostingEnabled ? 'activada' : 'desactivada'));
        } else {
            session()->flash('error', 'Error al cambiar el estado de publicación en página.');
            $this->pagePostingEnabled = !$this->pagePostingEnabled; // Revert
        }
    }

    public function render()
    {
        return view('livewire.settings')->layout('components.layouts.app', ['title' => 'Settings']);
    }
}
