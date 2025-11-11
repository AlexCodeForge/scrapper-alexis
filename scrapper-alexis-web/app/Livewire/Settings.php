<?php

namespace App\Livewire;

use App\Models\ScraperSettings;
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
    public $facebookProfilesList = [];
    public $newProfileUrl = '';
    public $twitterEmail = '';
    public $twitterPassword = '';
    public $twitterDisplayName = '';
    public $twitterUsername = '';
    public $twitterAvatarUrl = '';
    public $twitterVerified = false;
    public $tweetTemplatePaddingEnabled = false;
    public $proxyServer = '';
    public $proxyUsername = '';
    public $proxyPassword = '';

    // Debug Output Settings (per script)
    public $facebookDebugEnabled = false;
    public $twitterDebugEnabled = false;
    public $pagePostingDebugEnabled = false;

    // Facebook Page Posting Settings
    public $pageName = '';
    public $pageUrl = '';
    public $pageIntervalMin = 60;
    public $pageIntervalMax = 120;
    public $pagePostingEnabled = false;
    public $autoCleanupEnabled = false;
    public $cleanupDays = 7;

    // Image Generator Settings
    public $imageGeneratorEnabled = false;
    public $imageGeneratorIntervalMin = 30;
    public $imageGeneratorIntervalMax = 60;
    public $imageGeneratorDebugEnabled = false;

    // Application Settings
    public $timezone = 'America/Mexico_City';

    // Posting Operating Hours (12-hour format)
    public $postingStartHour = 7;
    public $postingStartPeriod = 'AM';
    public $postingStopHour = 1;
    public $postingStopPeriod = 'AM';

    // File uploads for auth
    public $facebookAuthFile;
    
    // Avatar upload for image generator
    public $avatarUpload;

    // Track if auth files exist
    public $facebookAuthExists = false;

    public function mount()
    {
        $this->loadSettings();
        $this->loadPagePostingSettings();
        $this->checkAuthFiles();
    }

    /**
     * Load scraper settings from database (not .env anymore)
     */
    public function loadSettings()
    {
        $settings = ScraperSettings::getSettings();

        $this->facebookIntervalMin = $settings->facebook_interval_min;
        $this->facebookIntervalMax = $settings->facebook_interval_max;
        $this->facebookEnabled = $settings->facebook_enabled;
        $this->facebookEmail = $settings->facebook_email ?? '';
        $this->facebookPassword = $settings->facebook_password ?? '';
        
        // Parse facebook_profiles (comma-separated or newline-separated)
        if ($settings->facebook_profiles) {
            $this->facebookProfiles = str_replace(',', "\n", $settings->facebook_profiles);
            $this->facebookProfilesList = array_filter(
                explode(',', str_replace("\n", ',', $settings->facebook_profiles))
            );
        }

        $this->twitterIntervalMin = $settings->twitter_interval_min;
        $this->twitterIntervalMax = $settings->twitter_interval_max;
        $this->twitterEnabled = $settings->twitter_enabled;
        $this->twitterEmail = $settings->twitter_email ?? '';
        $this->twitterPassword = $settings->twitter_password ?? '';
        $this->twitterDisplayName = $settings->display_name ?? '';
        $this->twitterUsername = $settings->username ?? '';
        $this->twitterAvatarUrl = $settings->avatar_url ?? '';
        $this->twitterVerified = $settings->verified ?? false;
        $this->tweetTemplatePaddingEnabled = $settings->tweet_template_padding_enabled ?? false;

        $this->proxyServer = $settings->proxy_server ?? '';
        $this->proxyUsername = $settings->proxy_username ?? '';
        $this->proxyPassword = $settings->proxy_password ?? '';

        // Load debug settings
        $this->facebookDebugEnabled = $settings->facebook_debug_enabled ?? false;
        $this->twitterDebugEnabled = $settings->twitter_debug_enabled ?? false;
        $this->pagePostingDebugEnabled = $settings->page_posting_debug_enabled ?? false;

        // Load image generator settings
        $this->imageGeneratorEnabled = $settings->image_generator_enabled ?? false;
        $this->imageGeneratorIntervalMin = $settings->image_generator_interval_min ?? 30;
        $this->imageGeneratorIntervalMax = $settings->image_generator_interval_max ?? 60;
        $this->imageGeneratorDebugEnabled = $settings->image_generator_debug_enabled ?? false;

        // Load application settings
        $this->timezone = $settings->timezone ?? 'America/Mexico_City';

        // Load posting operating hours
        $this->postingStartHour = $settings->posting_start_hour ?? 7;
        $this->postingStartPeriod = $settings->posting_start_period ?? 'AM';
        $this->postingStopHour = $settings->posting_stop_hour ?? 1;
        $this->postingStopPeriod = $settings->posting_stop_period ?? 'AM';

        \Log::info('Settings: Loaded from database', [
            'facebook_enabled' => $this->facebookEnabled,
            'twitter_enabled' => $this->twitterEnabled,
            'facebook_debug' => $this->facebookDebugEnabled,
            'twitter_debug' => $this->twitterDebugEnabled,
            'page_posting_debug' => $this->pagePostingDebugEnabled,
            'image_generator_enabled' => $this->imageGeneratorEnabled,
            'posting_hours' => "{$this->postingStartHour} {$this->postingStartPeriod} - {$this->postingStopHour} {$this->postingStopPeriod}"
        ]);
    }

    public function loadPagePostingSettings()
    {
        $postingService = app(PostingService::class);
        $settings = $postingService->getSettings();

        $this->pageName = $settings->page_name ?? '';
        $this->pageUrl = $settings->page_url ?? '';
        $this->pageIntervalMin = $settings->interval_min;
        $this->pageIntervalMax = $settings->interval_max;
        $this->pagePostingEnabled = $settings->enabled;
        $this->autoCleanupEnabled = $settings->auto_cleanup_enabled ?? false;
        $this->cleanupDays = $settings->cleanup_days ?? 7;
    }

    public function checkAuthFiles()
    {
        $pythonPath = config('scraper.python_path');
        $authPath = $pythonPath . '/scrapper-alexis/auth/auth_facebook.json';
        $this->facebookAuthExists = file_exists($authPath);
    }

    // Bugfix: Explicit toggle methods (Livewire $toggle magic doesn't trigger updated* hooks)
    public function toggleFacebook()
    {
        $this->facebookEnabled = !$this->facebookEnabled;

        // Update database instead of .env file
        $result = ScraperSettings::updateSettings([
            'facebook_enabled' => $this->facebookEnabled
        ]);

        if (!$result) {
            session()->flash('error', 'Error: No se pudo actualizar el estado del scraper de Facebook.');
            $this->facebookEnabled = !$this->facebookEnabled; // Revert
            return;
        }

        \Log::info('Settings: Facebook scraper toggled', ['enabled' => $this->facebookEnabled]);
        session()->flash('success', '✓ Facebook scraper ' . ($this->facebookEnabled ? 'activado' : 'desactivado'));
    }

    public function toggleTwitter()
    {
        $this->twitterEnabled = !$this->twitterEnabled;

        // Update database instead of .env file
        $result = ScraperSettings::updateSettings([
            'twitter_enabled' => $this->twitterEnabled
        ]);

        if (!$result) {
            session()->flash('error', 'Error: No se pudo actualizar el estado del publicador de Twitter.');
            $this->twitterEnabled = !$this->twitterEnabled; // Revert
            return;
        }

        \Log::info('Settings: Twitter poster toggled', ['enabled' => $this->twitterEnabled]);
        session()->flash('success', '✓ Twitter poster ' . ($this->twitterEnabled ? 'activado' : 'desactivado'));
    }

    public function toggleFacebookDebug()
    {
        $this->facebookDebugEnabled = !$this->facebookDebugEnabled;

        // Update database
        $result = ScraperSettings::updateSettings([
            'facebook_debug_enabled' => $this->facebookDebugEnabled
        ]);

        if (!$result) {
            session()->flash('error', 'Error: No se pudo actualizar el debug de Facebook.');
            $this->facebookDebugEnabled = !$this->facebookDebugEnabled; // Revert
            return;
        }

        \Log::info('Settings: Facebook debug toggled', ['enabled' => $this->facebookDebugEnabled]);
        session()->flash('success', '✓ Debug de Facebook ' . ($this->facebookDebugEnabled ? 'activado' : 'desactivado'));
    }

    public function toggleTwitterDebug()
    {
        $this->twitterDebugEnabled = !$this->twitterDebugEnabled;

        // Update database
        $result = ScraperSettings::updateSettings([
            'twitter_debug_enabled' => $this->twitterDebugEnabled
        ]);

        if (!$result) {
            session()->flash('error', 'Error: No se pudo actualizar el debug de Twitter.');
            $this->twitterDebugEnabled = !$this->twitterDebugEnabled; // Revert
            return;
        }

        \Log::info('Settings: Twitter debug toggled', ['enabled' => $this->twitterDebugEnabled]);
        session()->flash('success', '✓ Debug de Twitter ' . ($this->twitterDebugEnabled ? 'activado' : 'desactivado'));
    }

    public function togglePagePostingDebug()
    {
        $this->pagePostingDebugEnabled = !$this->pagePostingDebugEnabled;

        // Update database
        $result = ScraperSettings::updateSettings([
            'page_posting_debug_enabled' => $this->pagePostingDebugEnabled
        ]);

        if (!$result) {
            session()->flash('error', 'Error: No se pudo actualizar el debug de Page Posting.');
            $this->pagePostingDebugEnabled = !$this->pagePostingDebugEnabled; // Revert
            return;
        }

        \Log::info('Settings: Page posting debug toggled', ['enabled' => $this->pagePostingDebugEnabled]);
        session()->flash('success', '✓ Debug de Page Posting ' . ($this->pagePostingDebugEnabled ? 'activado' : 'desactivado'));
    }

    public function toggleImageGenerator()
    {
        $this->imageGeneratorEnabled = !$this->imageGeneratorEnabled;

        // Update database
        $result = ScraperSettings::updateSettings([
            'image_generator_enabled' => $this->imageGeneratorEnabled
        ]);

        if (!$result) {
            session()->flash('error', 'Error: No se pudo actualizar el estado del generador de imágenes.');
            $this->imageGeneratorEnabled = !$this->imageGeneratorEnabled; // Revert
            return;
        }

        \Log::info('Settings: Image generator toggled', ['enabled' => $this->imageGeneratorEnabled]);
        session()->flash('success', '✓ Generador de imágenes ' . ($this->imageGeneratorEnabled ? 'activado' : 'desactivado'));
    }

    public function toggleImageGeneratorDebug()
    {
        $this->imageGeneratorDebugEnabled = !$this->imageGeneratorDebugEnabled;

        // Update database
        $result = ScraperSettings::updateSettings([
            'image_generator_debug_enabled' => $this->imageGeneratorDebugEnabled
        ]);

        if (!$result) {
            session()->flash('error', 'Error: No se pudo actualizar el debug del generador de imágenes.');
            $this->imageGeneratorDebugEnabled = !$this->imageGeneratorDebugEnabled; // Revert
            return;
        }

        \Log::info('Settings: Image generator debug toggled', ['enabled' => $this->imageGeneratorDebugEnabled]);
        session()->flash('success', '✓ Debug de generador de imágenes ' . ($this->imageGeneratorDebugEnabled ? 'activado' : 'desactivado'));
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

        // Parse facebook_profiles (newline-separated to comma-separated)
        $profilesCommaSeparated = str_replace("\n", ',', trim($this->facebookProfiles));

        // Update database instead of .env file
        $result = ScraperSettings::updateSettings([
            'facebook_interval_min' => $this->facebookIntervalMin,
            'facebook_interval_max' => $this->facebookIntervalMax,
            'facebook_enabled' => $this->facebookEnabled,
            'facebook_email' => $this->facebookEmail,
            'facebook_password' => $this->facebookPassword, // Encrypted by model
            'facebook_profiles' => $profilesCommaSeparated,
            'twitter_interval_min' => $this->twitterIntervalMin,
            'twitter_interval_max' => $this->twitterIntervalMax,
            'twitter_enabled' => $this->twitterEnabled,
            'twitter_email' => $this->twitterEmail,
            'twitter_password' => $this->twitterPassword, // Encrypted by model
            'display_name' => $this->twitterDisplayName,
            'username' => $this->twitterUsername,
            'avatar_url' => $this->twitterAvatarUrl,
            'verified' => $this->twitterVerified,
            'proxy_server' => $this->proxyServer,
            'proxy_username' => $this->proxyUsername,
            'proxy_password' => $this->proxyPassword, // Encrypted by model
        ]);

        if ($result) {
            \Log::info('Settings: All settings saved to database successfully');
            session()->flash('success', 'Configuración guardada exitosamente en la base de datos.');
        } else {
            \Log::error('Settings: Failed to save settings to database');
            session()->flash('error', 'Error al guardar la configuración. Por favor, intente de nuevo.');
        }
    }

    /**
     * =============================================================================
     * ⚠️  DEPRECATED METHODS - NO LONGER USED ⚠️
     * =============================================================================
     * Twitter authentication upload/delete methods are no longer used.
     * The app now uses user-provided profile info instead of Twitter authentication.
     * 
     * These methods are kept commented out for reference only.
     * =============================================================================
     */
    
    // public function uploadTwitterAuth()
    // {
    //     \Log::info("uploadTwitterAuth: Method called");
    //     $this->validate([
    //         'twitterAuthFile' => 'required|file|mimes:json|max:2048',
    //     ]);
    //     try {
    //         $authPath = '/var/www/alexis-scrapper-docker/scrapper-alexis/auth/auth_x.json';
    //         $authDir = dirname($authPath);
    //         if (!is_dir($authDir)) {
    //             \Log::info("uploadTwitterAuth: Creating auth directory", ['dir' => $authDir]);
    //             mkdir($authDir, 0755, true);
    //         }
    //         $this->twitterAuthFile->storeAs('', 'auth_x.json', ['disk' => 'scraper_auth']);
    //         if (file_exists($authPath)) {
    //             chown($authPath, 'www-data');
    //             chgrp($authPath, 'www-data');
    //             \Log::info("uploadTwitterAuth: File saved and ownership set", ['auth' => $authPath]);
    //         } else {
    //             \Log::error("uploadTwitterAuth: File not found after upload", ['auth' => $authPath]);
    //             throw new \Exception("El archivo no se guardó correctamente en el servidor");
    //         }
    //         $sessionPath = '/var/www/alexis-scrapper-docker/scrapper-alexis/auth/auth_x_session.json';
    //         file_put_contents($sessionPath, json_encode([
    //             'username' => '',
    //             'display_name' => '',
    //             'avatar_url' => '',
    //             'login_time' => time(),
    //             'success' => true
    //         ], JSON_PRETTY_PRINT));
    //         if (file_exists($sessionPath)) {
    //             chown($sessionPath, 'www-data');
    //             chgrp($sessionPath, 'www-data');
    //             \Log::info("uploadTwitterAuth: Session file created and ownership set", ['session' => $sessionPath]);
    //         } else {
    //             \Log::warning("uploadTwitterAuth: Session file not created", ['session' => $sessionPath]);
    //         }
    //         $this->reset(['twitterAuthFile']);
    //         $this->checkAuthFiles();
    //         session()->flash('success', '✅ Archivo de autenticación de Twitter subido correctamente! Ejecuta el Twitter Poster para completar la configuración del perfil.');
    //     } catch (\Exception $e) {
    //         \Log::error("uploadTwitterAuth: Failed", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    //         session()->flash('error', 'Error al subir archivo de autenticación: ' . $e->getMessage());
    //     }
    // }

    // public function deleteTwitterAuth()
    // {
    //     \Log::info("deleteTwitterAuth: Method called");
    //     try {
    //         $authPath = '/var/www/alexis-scrapper-docker/scrapper-alexis/auth/auth_x.json';
    //         $sessionPath = '/var/www/alexis-scrapper-docker/scrapper-alexis/auth/auth_x_session.json';
    //         if (file_exists($authPath)) {
    //             unlink($authPath);
    //         }
    //         if (file_exists($sessionPath)) {
    //             unlink($sessionPath);
    //         }
    //         \Log::info("deleteTwitterAuth: Files deleted");
    //         $this->checkAuthFiles();
    //         session()->flash('success', '✅ Autenticación de Twitter eliminada correctamente.');
    //     } catch (\Exception $e) {
    //         \Log::error("deleteTwitterAuth: Failed", ['error' => $e->getMessage()]);
    //         session()->flash('error', 'Error al eliminar autenticación: ' . $e->getMessage());
    //     }
    // }

    public function uploadFacebookAuth()
    {
        \Log::info("uploadFacebookAuth: Method called");

        $this->validate([
            'facebookAuthFile' => 'required|file|mimes:json|max:2048',
        ]);

        try {
            $pythonPath = config('scraper.python_path');
            $authPath = $pythonPath . '/scrapper-alexis/auth/auth_facebook.json';
            $authDir = dirname($authPath);

            // Bugfix: Ensure the auth directory exists before saving files
            if (!is_dir($authDir)) {
                \Log::info("uploadFacebookAuth: Creating auth directory", ['dir' => $authDir]);
                mkdir($authDir, 0755, true);
            }

            // Save uploaded file
            $this->facebookAuthFile->storeAs('', 'auth_facebook.json', ['disk' => 'scraper_auth']);

            // Bugfix: Verify file exists before changing ownership
            if (file_exists($authPath)) {
                chown($authPath, 'www-data');
                chgrp($authPath, 'www-data');
                \Log::info("uploadFacebookAuth: File saved and ownership set", ['auth' => $authPath]);
            } else {
                \Log::error("uploadFacebookAuth: File not found after upload", ['auth' => $authPath]);
                throw new \Exception("El archivo no se guardó correctamente en el servidor");
            }

            // Create empty session file (will be populated on first run)
            $sessionPath = $pythonPath . '/scrapper-alexis/auth/auth_facebook_session.json';
            file_put_contents($sessionPath, json_encode([
                'session_storage' => []
            ], JSON_PRETTY_PRINT));

            // Bugfix: Verify session file exists before changing ownership
            if (file_exists($sessionPath)) {
                chown($sessionPath, 'www-data');
                chgrp($sessionPath, 'www-data');
                \Log::info("uploadFacebookAuth: Session file created and ownership set", ['session' => $sessionPath]);
            } else {
                \Log::warning("uploadFacebookAuth: Session file not created", ['session' => $sessionPath]);
            }

            // Clear uploaded file property
            $this->reset(['facebookAuthFile']);

            // Update status
            $this->checkAuthFiles();

            session()->flash('success', '✅ Archivo de autenticación de Facebook subido correctamente! El scraper usará esta sesión automáticamente.');

        } catch (\Exception $e) {
            \Log::error("uploadFacebookAuth: Failed", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            session()->flash('error', 'Error al subir archivo de autenticación: ' . $e->getMessage());
        }
    }

    public function deleteFacebookAuth()
    {
        \Log::info("deleteFacebookAuth: Method called");

        try {
            $pythonPath = config('scraper.python_path');
            $authPath = $pythonPath . '/scrapper-alexis/auth/auth_facebook.json';
            $sessionPath = $pythonPath . '/scrapper-alexis/auth/auth_facebook_session.json';

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
        \Log::info('Settings: savePagePostingSettings called', [
            'page_name' => $this->pageName,
            'enabled' => $this->pagePostingEnabled,
            'interval_min' => $this->pageIntervalMin,
            'interval_max' => $this->pageIntervalMax
        ]);

        // Small delay to ensure loading modal is visible to users
        usleep(300000); // 300ms

        $this->validate([
            'pageName' => 'required|string|max:255',
            'pageUrl' => 'required|url:http,https|max:500',
            'pageIntervalMin' => 'required|integer|min:1|max:1440',
            'pageIntervalMax' => 'required|integer|min:1|max:1440|gte:pageIntervalMin',
            'cleanupDays' => 'required|integer|min:1|max:365',
        ]);

        $postingService = app(PostingService::class);

        $result = $postingService->updateSettings([
            'page_name' => $this->pageName,
            'page_url' => $this->pageUrl,
            'interval_min' => $this->pageIntervalMin,
            'interval_max' => $this->pageIntervalMax,
            'enabled' => $this->pagePostingEnabled,
            'auto_cleanup_enabled' => $this->autoCleanupEnabled,
            'cleanup_days' => $this->cleanupDays,
        ]);

        if ($result) {
            \Log::info('Settings: savePagePostingSettings success');
            $this->dispatch('settings-saved', message: 'Configuración de publicación en página guardada correctamente');
        } else {
            \Log::error('Settings: savePagePostingSettings failed');
            $this->dispatch('settings-error', message: 'Error al guardar la configuración de publicación en página');
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

    public function addProfileUrl()
    {
        $this->validate([
            'newProfileUrl' => 'required|url',
        ], [
            'newProfileUrl.required' => 'La URL es requerida',
            'newProfileUrl.url' => 'Debe ser una URL válida',
        ]);

        if (!in_array($this->newProfileUrl, $this->facebookProfilesList)) {
            $this->facebookProfilesList[] = $this->newProfileUrl;
            $this->newProfileUrl = '';
            session()->flash('success', 'URL agregada correctamente');
        } else {
            session()->flash('error', 'Esta URL ya existe en la lista');
        }
    }

    public function removeProfileUrl($index)
    {
        if (isset($this->facebookProfilesList[$index])) {
            unset($this->facebookProfilesList[$index]);
            $this->facebookProfilesList = array_values($this->facebookProfilesList); // Re-index array
            session()->flash('success', 'URL eliminada correctamente');
        }
    }

    public function saveFacebookSettings()
    {
        \Log::info('Settings: saveFacebookSettings called', ['email' => $this->facebookEmail, 'profiles_count' => count($this->facebookProfilesList)]);

        // Small delay to ensure loading modal is visible to users
        usleep(300000); // 300ms

        $profilesCommaSeparated = implode(',', $this->facebookProfilesList);

        // Update database instead of .env file
        $result = ScraperSettings::updateSettings([
            'facebook_email' => $this->facebookEmail,
            'facebook_password' => $this->facebookPassword, // Encrypted by model
            'facebook_profiles' => $profilesCommaSeparated,
        ]);

        if ($result) {
            \Log::info('Settings: saveFacebookSettings success');
            $this->dispatch('settings-saved', message: 'Configuración de Facebook guardada correctamente');
        } else {
            \Log::error('Settings: saveFacebookSettings failed');
            $this->dispatch('settings-error', message: 'Error al guardar configuración de Facebook');
        }
    }

    /**
     * Save Image Generator Settings (reusing Twitter fields for minimal changes)
     * - Display name, username, verified badge, and avatar upload
     */
    public function saveImageGeneratorSettings()
    {
        \Log::info('Settings: saveImageGeneratorSettings called', [
            'display_name' => $this->twitterDisplayName, 
            'username' => $this->twitterUsername, 
            'verified' => $this->twitterVerified
        ]);

        // Small delay to ensure loading modal is visible to users
        usleep(300000); // 300ms

        // Validate avatar upload if provided
        if ($this->avatarUpload) {
            $this->validate([
                'avatarUpload' => 'image|mimes:jpeg,jpg,png|max:2048',
            ]);
        }

        // Prepare data for update
        $updateData = [
            'display_name' => $this->twitterDisplayName,
            'username' => $this->twitterUsername,
            'verified' => $this->twitterVerified,
            'tweet_template_padding_enabled' => $this->tweetTemplatePaddingEnabled,
        ];
        
        // Bugfix: Log padding setting to debug save issue
        \Log::info('Bugfix: Saving image generator settings with padding', [
            'padding_enabled' => $this->tweetTemplatePaddingEnabled,
            'before_save' => $updateData
        ]);

        // Handle avatar upload
        if ($this->avatarUpload) {
            try {
                // Store avatar in public/storage/avatars/
                $avatarPath = $this->avatarUpload->store('avatars', 'public');
                $updateData['avatar_url'] = $avatarPath;
                
                // Copy avatar to Python project for image generation
                $publicAvatarPath = storage_path('app/public/' . $avatarPath);
                $pythonPath = config('scraper.python_path');
                $pythonAvatarPath = $pythonPath . '/scrapper-alexis/avatar_cache/user_avatar.jpg';
                
                // Ensure avatar_cache directory exists
                $pythonAvatarDir = dirname($pythonAvatarPath);
                if (!is_dir($pythonAvatarDir)) {
                    mkdir($pythonAvatarDir, 0755, true);
                }
                
                // Copy file
                if (file_exists($publicAvatarPath)) {
                    copy($publicAvatarPath, $pythonAvatarPath);
                    chmod($pythonAvatarPath, 0644);
                    \Log::info('Settings: Avatar copied to Python project', [
                        'from' => $publicAvatarPath,
                        'to' => $pythonAvatarPath
                    ]);
                }
                
                // Clear the upload property
                $this->reset(['avatarUpload']);
                
            } catch (\Exception $e) {
                \Log::error('Settings: Avatar upload failed', ['error' => $e->getMessage()]);
                $this->dispatch('settings-error', message: 'Error al subir el avatar: ' . $e->getMessage());
                return;
            }
        } else {
            // Bugfix: Even if no new avatar uploaded, ensure existing avatar is synced to Python project
            // This handles cases where avatar was uploaded before but copy failed
            if ($this->twitterAvatarUrl) {
                try {
                    $publicAvatarPath = storage_path('app/public/' . $this->twitterAvatarUrl);
                    $pythonPath = config('scraper.python_path');
                    $pythonAvatarPath = $pythonPath . '/scrapper-alexis/avatar_cache/user_avatar.jpg';
                    
                    // Ensure avatar_cache directory exists
                    $pythonAvatarDir = dirname($pythonAvatarPath);
                    if (!is_dir($pythonAvatarDir)) {
                        mkdir($pythonAvatarDir, 0755, true);
                    }
                    
                    // Copy file if source exists and destination doesn't exist or is older
                    if (file_exists($publicAvatarPath)) {
                        if (!file_exists($pythonAvatarPath) || filemtime($publicAvatarPath) > filemtime($pythonAvatarPath)) {
                            copy($publicAvatarPath, $pythonAvatarPath);
                            chmod($pythonAvatarPath, 0644);
                            \Log::info('Settings: Synced existing avatar to Python project', [
                                'from' => $publicAvatarPath,
                                'to' => $pythonAvatarPath
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Settings: Failed to sync existing avatar', ['error' => $e->getMessage()]);
                    // Don't fail the whole save if avatar sync fails
                }
            }
        }

        // Update database
        $result = ScraperSettings::updateSettings($updateData);

        if ($result) {
            \Log::info('Settings: saveImageGeneratorSettings success');
            $this->dispatch('settings-saved', message: 'Configuración del generador de imágenes guardada correctamente');
            
            // Reload settings to show updated avatar URL
            $this->loadSettings();
        } else {
            \Log::error('Settings: saveImageGeneratorSettings failed');
            $this->dispatch('settings-error', message: 'Error al guardar configuración del generador de imágenes');
        }
    }
    
    /**
     * Legacy method - kept for backwards compatibility but redirects to new method
     * @deprecated Use saveImageGeneratorSettings() instead
     */
    public function saveTwitterSettings()
    {
        return $this->saveImageGeneratorSettings();
    }

    public function saveCronSettings()
    {
        \Log::info('Settings: saveCronSettings called', ['fb_min' => $this->facebookIntervalMin, 'tw_min' => $this->twitterIntervalMin]);

        // Small delay to ensure loading modal is visible to users
        usleep(300000); // 300ms

        $this->validate([
            'facebookIntervalMin' => 'required|integer|min:1|max:1440',
            'facebookIntervalMax' => 'required|integer|min:1|max:1440|gte:facebookIntervalMin',
            'twitterIntervalMin' => 'required|integer|min:1|max:1440',
            'twitterIntervalMax' => 'required|integer|min:1|max:1440|gte:twitterIntervalMin',
            'imageGeneratorIntervalMin' => 'required|integer|min:1|max:1440',
            'imageGeneratorIntervalMax' => 'required|integer|min:1|max:1440|gte:imageGeneratorIntervalMin',
        ]);

        // Update database instead of .env file
        $result = ScraperSettings::updateSettings([
            'facebook_interval_min' => $this->facebookIntervalMin,
            'facebook_interval_max' => $this->facebookIntervalMax,
            'twitter_interval_min' => $this->twitterIntervalMin,
            'twitter_interval_max' => $this->twitterIntervalMax,
            'image_generator_interval_min' => $this->imageGeneratorIntervalMin,
            'image_generator_interval_max' => $this->imageGeneratorIntervalMax,
        ]);

        if ($result) {
            \Log::info('Settings: saveCronSettings success');
            $this->dispatch('settings-saved', message: 'Configuración de cron guardada correctamente');
        } else {
            \Log::error('Settings: saveCronSettings failed');
            $this->dispatch('settings-error', message: 'Error al guardar intervalos de cron');
        }
    }

    public function saveProxySettings()
    {
        \Log::info('Settings: saveProxySettings called', ['server' => $this->proxyServer ? 'set' : 'empty']);

        // Small delay to ensure loading modal is visible to users
        usleep(300000); // 300ms

        // Update database instead of .env file
        $result = ScraperSettings::updateSettings([
            'proxy_server' => $this->proxyServer,
            'proxy_username' => $this->proxyUsername,
            'proxy_password' => $this->proxyPassword, // Encrypted by model
        ]);

        if ($result) {
            \Log::info('Settings: saveProxySettings success');
            $this->dispatch('settings-saved', message: 'Configuración de proxy guardada correctamente');
        } else {
            \Log::error('Settings: saveProxySettings failed');
            $this->dispatch('settings-error', message: 'Error al guardar configuración de proxy');
        }
    }

    public function saveApplicationSettings()
    {
        \Log::info('Settings: saveApplicationSettings called', ['timezone' => $this->timezone]);

        // Small delay to ensure loading modal is visible to users
        usleep(300000); // 300ms

        $this->validate([
            'timezone' => 'required|string|max:100',
        ]);

        // Update database
        $result = ScraperSettings::updateSettings([
            'timezone' => $this->timezone,
        ]);

        if ($result) {
            \Log::info('Settings: saveApplicationSettings success');
            // Clear config cache to apply timezone immediately
            \Artisan::call('config:cache');
            $this->dispatch('settings-saved', message: 'Configuración de aplicación guardada correctamente. Zona horaria actualizada.');
        } else {
            \Log::error('Settings: saveApplicationSettings failed');
            $this->dispatch('settings-error', message: 'Error al guardar configuración de aplicación');
        }
    }

    public function saveOperatingHoursSettings()
    {
        \Log::info('Settings: saveOperatingHoursSettings called', [
            'start' => "{$this->postingStartHour} {$this->postingStartPeriod}",
            'stop' => "{$this->postingStopHour} {$this->postingStopPeriod}"
        ]);

        // Small delay to ensure loading modal is visible to users
        usleep(300000); // 300ms

        $this->validate([
            'postingStartHour' => 'required|integer|min:1|max:12',
            'postingStartPeriod' => 'required|in:AM,PM',
            'postingStopHour' => 'required|integer|min:1|max:12',
            'postingStopPeriod' => 'required|in:AM,PM',
        ]);

        // Update database
        $result = ScraperSettings::updateSettings([
            'posting_start_hour' => $this->postingStartHour,
            'posting_start_period' => $this->postingStartPeriod,
            'posting_stop_hour' => $this->postingStopHour,
            'posting_stop_period' => $this->postingStopPeriod,
        ]);

        if ($result) {
            \Log::info('Settings: saveOperatingHoursSettings success');
            $this->dispatch('settings-saved', message: 'Horario de funcionamiento guardado correctamente. Las publicaciones se detendrán a las ' . $this->postingStopHour . ' ' . $this->postingStopPeriod . ' y comenzarán a las ' . $this->postingStartHour . ' ' . $this->postingStartPeriod . '.');
        } else {
            \Log::error('Settings: saveOperatingHoursSettings failed');
            $this->dispatch('settings-error', message: 'Error al guardar horario de funcionamiento');
        }
    }

    public function testProxyConnection()
    {
        \Log::info('Settings: testProxyConnection called', [
            'server' => $this->proxyServer,
            'username' => $this->proxyUsername ? 'set' : 'empty'
        ]);

        // Validate proxy configuration exists
        if (empty($this->proxyServer)) {
            \Log::warning('Settings: testProxyConnection - No proxy server configured');
            $this->dispatch('proxy-test-result',
                success: false,
                message: 'Por favor, configure un servidor proxy primero',
                details: null
            );
            return;
        }

        try {
            $startTime = microtime(true);

            // Build proxy URL with authentication
            $proxyUrl = $this->proxyServer;
            if ($this->proxyUsername && $this->proxyPassword) {
                // Format: http://username:password@host:port
                $parsedUrl = parse_url($this->proxyServer);
                $scheme = $parsedUrl['scheme'] ?? 'http';
                $host = $parsedUrl['host'] ?? $this->proxyServer;
                $port = $parsedUrl['port'] ?? '';
                $portSuffix = $port ? ":{$port}" : '';
                
                $proxyUrl = "{$scheme}://{$this->proxyUsername}:{$this->proxyPassword}@{$host}{$portSuffix}";
            }

            \Log::info('Settings: testProxyConnection - Making test request through proxy');

            // Test proxy with a simple IP check endpoint
            $response = \Illuminate\Support\Facades\Http::withOptions([
                'proxy' => $proxyUrl,
                'timeout' => 10,
                'connect_timeout' => 5,
            ])->get('https://api.ipify.org?format=json');

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds

            if ($response->successful()) {
                $ipData = $response->json();
                $proxyIp = $ipData['ip'] ?? 'Unknown';

                \Log::info('Settings: testProxyConnection - Success', [
                    'proxy_ip' => $proxyIp,
                    'response_time' => $responseTime . 'ms'
                ]);

                $this->dispatch('proxy-test-result',
                    success: true,
                    message: '✅ Proxy funcionando correctamente',
                    details: [
                        'ip' => $proxyIp,
                        'response_time' => $responseTime . 'ms',
                        'status' => $response->status()
                    ]
                );
            } else {
                \Log::error('Settings: testProxyConnection - HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                $this->dispatch('proxy-test-result',
                    success: false,
                    message: '❌ Error de conexión con el proxy',
                    details: [
                        'status' => $response->status(),
                        'error' => 'HTTP ' . $response->status()
                    ]
                );
            }

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('Settings: testProxyConnection - Connection exception', [
                'error' => $e->getMessage()
            ]);

            $this->dispatch('proxy-test-result',
                success: false,
                message: '❌ No se pudo conectar al proxy',
                details: [
                    'error' => 'Timeout o proxy no responde'
                ]
            );

        } catch (\Exception $e) {
            \Log::error('Settings: testProxyConnection - Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->dispatch('proxy-test-result',
                success: false,
                message: '❌ Error al probar el proxy',
                details: [
                    'error' => $e->getMessage()
                ]
            );
        }
    }

    /**
     * Clear all messages and images from database and filesystem
     * This allows the scraper to start fresh
     */
    public function clearAllData()
    {
        \Log::info('Settings: clearAllData called - Starting data cleanup');

        try {
            // Get count of messages and images before deletion
            $messagesCount = \App\Models\Message::count();
            $imagesCount = \App\Models\Message::where('image_generated', true)->count();

            \Log::info('Settings: clearAllData - Current data stats', [
                'messages_count' => $messagesCount,
                'images_count' => $imagesCount
            ]);

            // Delete all image files from filesystem
            $imageDir = '/var/www/scrapper-alexis/data/message_images';
            $deletedFiles = 0;

            if (is_dir($imageDir)) {
                $files = glob($imageDir . '/*');
                
                foreach ($files as $file) {
                    if (is_file($file)) {
                        if (unlink($file)) {
                            $deletedFiles++;
                            \Log::info('Settings: clearAllData - Deleted image file', ['file' => basename($file)]);
                        } else {
                            \Log::warning('Settings: clearAllData - Failed to delete file', ['file' => $file]);
                        }
                    }
                }

                \Log::info('Settings: clearAllData - Image files deleted', ['count' => $deletedFiles]);
            } else {
                \Log::warning('Settings: clearAllData - Image directory does not exist', ['dir' => $imageDir]);
            }

            // Delete all messages from database (this will cascade delete related data)
            \App\Models\Message::truncate();
            \App\Models\ScrapingSession::truncate();

            \Log::info('Settings: clearAllData - Database tables truncated successfully', [
                'messages_deleted' => $messagesCount,
                'images_deleted_from_disk' => $deletedFiles
            ]);

            session()->flash('success', "✅ Todos los datos eliminados correctamente. Se eliminaron {$messagesCount} mensajes y {$deletedFiles} imágenes. El scraper puede comenzar desde cero.");

        } catch (\Exception $e) {
            \Log::error('Settings: clearAllData - Error during cleanup', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            session()->flash('error', 'Error al eliminar los datos: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.settings-modular')->layout('components.layouts.app', ['title' => 'Settings']);
    }
}
