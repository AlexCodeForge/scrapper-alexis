<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Mensajes Flash -->
            @if (session()->has('success'))
                <div x-data="{ show: true }"
                     x-show="show"
                     x-init="setTimeout(() => show = false, 5000); $el.scrollIntoView({ behavior: 'smooth', block: 'center' });"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-90"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-300"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-90"
                     class="mb-6 relative">
                    <x-alert class="border-2 border-green-500 bg-green-50 shadow-lg pr-10">
                        <x-lucide-circle-check class="h-5 w-5 text-green-600" />
                        <x-alert.title class="text-green-800">Éxito</x-alert.title>
                        <x-alert.description class="text-green-700">{{ session('success') }}</x-alert.description>
                    </x-alert>
                    <button @click="show = false" class="absolute top-3 right-3 text-green-600 hover:text-green-800 transition-colors">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            @endif

            @if (session()->has('warning'))
                <div x-data="{ show: true }"
                     x-show="show"
                     x-init="setTimeout(() => show = false, 5000); $el.scrollIntoView({ behavior: 'smooth', block: 'center' });"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-90"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-300"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-90"
                     class="mb-6 relative">
                    <x-alert class="border-2 border-yellow-500 bg-yellow-50 shadow-lg pr-10">
                        <x-lucide-alert-triangle class="h-5 w-5 text-yellow-600" />
                        <x-alert.title class="text-yellow-800">Advertencia</x-alert.title>
                        <x-alert.description class="text-yellow-700">{{ session('warning') }}</x-alert.description>
                    </x-alert>
                    <button @click="show = false" class="absolute top-3 right-3 text-yellow-600 hover:text-yellow-800 transition-colors">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            @endif

            @if (session()->has('error'))
                <div x-data="{ show: true }"
                     x-show="show"
                     x-init="setTimeout(() => show = false, 5000); $el.scrollIntoView({ behavior: 'smooth', block: 'center' });"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-90"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-300"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-90"
                     class="mb-6 relative">
                    <x-alert variant="destructive" class="border-2 border-red-500 bg-red-50 shadow-lg pr-10">
                        <x-lucide-triangle-alert class="h-5 w-5 text-red-600" />
                        <x-alert.title class="text-red-800">Error</x-alert.title>
                        <x-alert.description class="text-red-700">{{ session('error') }}</x-alert.description>
                    </x-alert>
                    <button @click="show = false" class="absolute top-3 right-3 text-red-600 hover:text-red-800 transition-colors">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            @endif

            <form wire:submit.prevent="saveSettings">
                <!-- Cronjob Control -->
                <x-card class="mb-6">
                    <x-card.header>
                        <x-card.title>Control de Cronjobs</x-card.title>
                        <x-card.description>Activar o desactivar trabajos automatizados</x-card.description>
                    </x-card.header>
                    <x-card.content>
                        <div class="space-y-4">
                            <!-- Facebook Scraper Toggle -->
                            <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition-colors gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <x-lucide-facebook class="h-5 w-5 text-blue-600" />
                                        <h4 class="font-medium text-gray-900">Facebook Scraper</h4>
                                    </div>
                                    <p class="text-sm text-gray-600">
                                        Estado:
                                        <span class="font-semibold {{ $facebookEnabled ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $facebookEnabled ? '🟢 Activo' : '🔴 Detenido' }}
                                        </span>
                                    </p>
                                    @if($facebookEnabled)
                                        <p class="text-xs text-gray-500 mt-1">Se ejecuta cada {{ $facebookIntervalMin }}-{{ $facebookIntervalMax }} minutos</p>
                                    @else
                                        <p class="text-xs text-gray-500 mt-1">El cronjob está deshabilitado</p>
                                    @endif
                                </div>
                                <div class="flex-shrink-0">
                                    <button type="button" wire:click="toggleFacebook"
                                            style="width: 60px; height: 34px; border-radius: 17px; position: relative; transition: all 0.3s; cursor: pointer; {{ $facebookEnabled ? 'background-color: #16a34a;' : 'background-color: #d1d5db;' }}"
                                            class="focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                            title="Click para {{ $facebookEnabled ? 'desactivar' : 'activar' }}">
                                        <span style="position: absolute; top: 3px; {{ $facebookEnabled ? 'left: 28px;' : 'left: 3px;' }} width: 28px; height: 28px; background-color: white; border-radius: 50%; transition: all 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></span>
                                    </button>
                                </div>
                            </div>

                            <!-- Twitter Poster Toggle -->
                            <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition-colors gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <x-lucide-twitter class="h-5 w-5 text-blue-400" />
                                        <h4 class="font-medium text-gray-900">Twitter Poster</h4>
                                    </div>
                                    <p class="text-sm text-gray-600">
                                        Estado:
                                        <span class="font-semibold {{ $twitterEnabled ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $twitterEnabled ? '🟢 Activo' : '🔴 Detenido' }}
                                        </span>
                                    </p>
                                    @if($twitterEnabled)
                                        <p class="text-xs text-gray-500 mt-1">Se ejecuta cada {{ $twitterIntervalMin }}-{{ $twitterIntervalMax }} minutos</p>
                                    @else
                                        <p class="text-xs text-gray-500 mt-1">El cronjob está deshabilitado</p>
                                    @endif
                                </div>
                                <div class="flex-shrink-0">
                                    <button type="button" wire:click="toggleTwitter"
                                            style="width: 60px; height: 34px; border-radius: 17px; position: relative; transition: all 0.3s; cursor: pointer; {{ $twitterEnabled ? 'background-color: #16a34a;' : 'background-color: #d1d5db;' }}"
                                            class="focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                            title="Click para {{ $twitterEnabled ? 'desactivar' : 'activar' }}">
                                        <span style="position: absolute; top: 3px; {{ $twitterEnabled ? 'left: 28px;' : 'left: 3px;' }} width: 28px; height: 28px; background-color: white; border-radius: 50%; transition: all 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </x-card.content>
                </x-card>

                <!-- Intervalos de Cron -->
                <x-card class="mb-6">
                    <x-card.header>
                        <x-card.title>Programación de Cron</x-card.title>
                        <x-card.description>Configurar intervalos de tareas automatizadas (en minutos)</x-card.description>
                    </x-card.header>
                    <x-card.content>
                        <div class="space-y-6">
                            <!-- Facebook Interval Range -->
                            <div class="space-y-3">
                                <x-label class="flex items-center gap-2">
                                    <x-lucide-clock class="h-4 w-4" />
                                    Intervalo de Scraper de Facebook (minutos)
                                </x-label>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <x-label for="facebookIntervalMin" class="text-xs text-muted-foreground">Mínimo</x-label>
                                        <x-input type="number" id="facebookIntervalMin" wire:model.blur="facebookIntervalMin" min="1" max="1440" />
                                        @error('facebookIntervalMin') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="space-y-2">
                                        <x-label for="facebookIntervalMax" class="text-xs text-muted-foreground">Máximo</x-label>
                                        <x-input type="number" id="facebookIntervalMax" wire:model.blur="facebookIntervalMax" min="1" max="1440" />
                                        @error('facebookIntervalMax') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">El scraper se ejecutará aleatoriamente entre {{ $facebookIntervalMin }} y {{ $facebookIntervalMax }} minutos</p>
                            </div>

                            <!-- Twitter Interval Range -->
                            <div class="space-y-3">
                                <x-label class="flex items-center gap-2">
                                    <x-lucide-clock class="h-4 w-4" />
                                    Intervalo de Publicador de Twitter (minutos)
                                </x-label>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <x-label for="twitterIntervalMin" class="text-xs text-muted-foreground">Mínimo</x-label>
                                        <x-input type="number" id="twitterIntervalMin" wire:model.blur="twitterIntervalMin" min="1" max="1440" />
                                        @error('twitterIntervalMin') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="space-y-2">
                                        <x-label for="twitterIntervalMax" class="text-xs text-muted-foreground">Máximo</x-label>
                                        <x-input type="number" id="twitterIntervalMax" wire:model.blur="twitterIntervalMax" min="1" max="1440" />
                                        @error('twitterIntervalMax') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">El publicador se ejecutará aleatoriamente entre {{ $twitterIntervalMin }} y {{ $twitterIntervalMax }} minutos</p>
                            </div>
                        </div>
                    </x-card.content>
                </x-card>

                <!-- Cuenta de Facebook -->
                <x-card class="mb-6">
                    <x-card.header>
                        <x-card.title class="flex items-center gap-2">
                            <x-lucide-facebook class="h-5 w-5 text-blue-600" />
                            Cuenta de Facebook
                        </x-card.title>
                        <x-card.description>Configurar credenciales del scraper de Facebook</x-card.description>
                    </x-card.header>
                    <x-card.content class="space-y-4">
                        <div class="space-y-2">
                            <x-label for="facebookEmail">Correo electrónico</x-label>
                            <x-input type="text" id="facebookEmail" wire:model.blur="facebookEmail" />
                            @error('facebookEmail') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-2">
                            <x-label for="facebookPassword">Contraseña</x-label>
                            <x-input type="text" id="facebookPassword" wire:model.blur="facebookPassword" />
                            @error('facebookPassword') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-2">
                            <x-label for="facebookProfiles">URLs de perfiles</x-label>
                            <x-textarea id="facebookProfiles" wire:model.blur="facebookProfiles" rows="4"
                                placeholder="https://facebook.com/perfil1&#10;https://facebook.com/perfil2" />
                            @error('facebookProfiles') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-muted-foreground">Una URL por línea</p>
                        </div>
                    </x-card.content>
                </x-card>

                <!-- Cuenta de Twitter -->
                <x-card class="mb-6">
                    <x-card.header>
                        <x-card.title class="flex items-center gap-2">
                            <x-lucide-twitter class="h-5 w-5 text-blue-400" />
                            Cuenta de Twitter
                        </x-card.title>
                        <x-card.description>Configurar credenciales del publicador de Twitter</x-card.description>
                    </x-card.header>
                    <x-card.content class="space-y-4">
                        <div class="space-y-2">
                            <x-label for="twitterEmail">Correo electrónico</x-label>
                            <x-input type="text" id="twitterEmail" wire:model.blur="twitterEmail" />
                            @error('twitterEmail') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-2">
                            <x-label for="twitterPassword">Contraseña</x-label>
                            <x-input type="text" id="twitterPassword" wire:model.blur="twitterPassword" />
                            @error('twitterPassword') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </x-card.content>
                </x-card>

                <!-- Configuración de Proxy -->
                <x-card class="mb-6">
                    <x-card.header>
                        <x-card.title class="flex items-center gap-2">
                            <x-lucide-globe class="h-5 w-5 text-purple-500" />
                            Configuración de Proxy
                        </x-card.title>
                        <x-card.description>Configurar servidor proxy para extracción</x-card.description>
                    </x-card.header>
                    <x-card.content class="space-y-4">
                        <div class="space-y-2">
                            <x-label for="proxyServer">Servidor Proxy</x-label>
                            <x-input type="text" id="proxyServer" wire:model.blur="proxyServer"
                                placeholder="http://proxy.ejemplo.com:8080" />
                            @error('proxyServer') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <x-label for="proxyUsername">Usuario</x-label>
                                <x-input type="text" id="proxyUsername" wire:model.blur="proxyUsername" />
                                @error('proxyUsername') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="space-y-2">
                                <x-label for="proxyPassword">Contraseña</x-label>
                                <x-input type="text" id="proxyPassword" wire:model.blur="proxyPassword" />
                                @error('proxyPassword') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </x-card.content>
                </x-card>

                <!-- Botón Guardar Configuración Principal -->
                <div class="flex justify-end mb-6">
                    <x-button type="submit" size="lg">
                        <x-lucide-save class="mr-2 h-4 w-4" />
                        Guardar Configuración
                    </x-button>
                </div>
            </form>

            <!-- Application Settings (Timezone) - Separate Form -->
            <form wire:submit="saveApplicationSettings" class="mb-6">
                <x-card class="mb-6">
                    <x-card.header>
                        <x-card.title class="flex items-center gap-2">
                            <x-lucide-clock class="h-5 w-5 text-indigo-500" />
                            Configuración de Aplicación
                        </x-card.title>
                        <x-card.description>Configurar zona horaria para la aplicación</x-card.description>
                    </x-card.header>
                    <x-card.content class="space-y-4">
                        <div class="space-y-2">
                            <x-label for="timezone">Zona Horaria</x-label>
                            <select id="timezone" wire:model.blur="timezone"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                <option value="America/Mexico_City">Ciudad de México (UTC-6/-5)</option>
                                <option value="America/Cancun">Cancún (UTC-5)</option>
                                <option value="America/Chihuahua">Chihuahua (UTC-7/-6)</option>
                                <option value="America/Tijuana">Tijuana (UTC-8/-7)</option>
                                <option value="America/Mazatlan">Mazatlán (UTC-7/-6)</option>
                                <option value="America/Monterrey">Monterrey (UTC-6/-5)</option>
                                <option value="Europe/Madrid">Madrid (UTC+1/+2)</option>
                                <option value="Europe/London">Londres (UTC+0/+1)</option>
                                <option value="America/New_York">Nueva York (UTC-5/-4)</option>
                                <option value="America/Los_Angeles">Los Ángeles (UTC-8/-7)</option>
                                <option value="UTC">UTC (Coordinado Universal)</option>
                            </select>
                            @error('timezone') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-muted-foreground mt-1">
                                Esta configuración afecta cómo se muestran las fechas y horas en toda la aplicación.
                            </p>
                        </div>
                    </x-card.content>
                    <x-card.footer class="flex justify-end">
                        <x-button type="submit">
                            <x-lucide-save class="mr-2 h-4 w-4" />
                            Guardar Zona Horaria
                        </x-button>
                    </x-card.footer>
                </x-card>
            </form>

            <!-- Facebook Authentication Upload (Separate Form) -->
            <x-card class="mb-6">
                    <x-card.header>
                        <x-card.title class="flex items-center gap-2">
                            <x-lucide-facebook class="h-5 w-5 text-blue-600" />
                            Autenticación de Facebook
                        </x-card.title>
                        <x-card.description>Gestionar archivo de sesión de Facebook</x-card.description>
                    </x-card.header>
                    <x-card.content class="space-y-4">
                        @if($facebookAuthExists)
                            <!-- Auth file exists - show status and delete option -->
                            <div class="rounded-lg bg-green-50 border border-green-200 p-4">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-green-800 mb-1">✅ Sesión activa</p>
                                        <p class="text-xs text-green-700">El archivo de autenticación está configurado. El scraper usará esta sesión automáticamente.</p>
                                    </div>
                                    <button
                                        wire:click="deleteFacebookAuth"
                                        wire:confirm="¿Estás seguro de eliminar la autenticación de Facebook?"
                                        class="text-red-600 hover:text-red-800 text-sm font-medium flex items-center gap-1"
                                        wire:loading.attr="disabled"
                                        wire:target="deleteFacebookAuth"
                                    >
                                        <x-lucide-trash-2 class="h-4 w-4" />
                                        <span wire:loading.remove wire:target="deleteFacebookAuth">Eliminar</span>
                                        <span wire:loading wire:target="deleteFacebookAuth">Eliminando...</span>
                                    </button>
                                </div>
                            </div>
                        @else
                            <!-- No auth file - show upload form -->
                            <div class="rounded-lg bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800">
                                <p class="font-semibold mb-2">📋 Instrucciones:</p>
                                <ol class="list-decimal list-inside space-y-1 ml-2">
                                    <li>Ejecuta <code class="bg-blue-200 px-1 rounded">capture_facebook_session.py</code> en tu PC</li>
                                    <li>Inicia sesión en Facebook cuando se abra el navegador</li>
                                    <li>El script generará <code class="bg-blue-200 px-1 rounded">auth_facebook.json</code></li>
                                    <li>Sube el archivo aquí</li>
                                </ol>
                            </div>

                            <form wire:submit.prevent="uploadFacebookAuth" class="space-y-4">
                                <div class="space-y-2">
                                    <x-label for="facebookAuthFile">
                                        Archivo de Sesión (auth_facebook.json)
                                        <span class="text-destructive">*</span>
                                    </x-label>
                                    <input
                                        type="file"
                                        id="facebookAuthFile"
                                        wire:model="facebookAuthFile"
                                        accept=".json"
                                        class="block w-full text-sm text-slate-500
                                            file:mr-4 file:py-2 file:px-4
                                            file:rounded-md file:border-0
                                            file:text-sm file:font-semibold
                                            file:bg-blue-50 file:text-blue-700
                                            hover:file:bg-blue-100
                                            cursor-pointer"
                                    />
                                    @error('facebookAuthFile') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                                    <div wire:loading wire:target="facebookAuthFile" class="text-xs text-muted-foreground">
                                        Cargando archivo...
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <x-button
                                        type="submit"
                                        wire:loading.attr="disabled"
                                        wire:target="uploadFacebookAuth"
                                    >
                                        <x-lucide-upload class="mr-2 h-4 w-4" />
                                        <span wire:loading.remove wire:target="uploadFacebookAuth">Subir Archivo</span>
                                        <span wire:loading wire:target="uploadFacebookAuth">Subiendo...</span>
                                    </x-button>
                                </div>
                            </form>
                        @endif
                    </x-card.content>
                </x-card>

                <!-- Twitter Authentication Upload -->
                <x-card class="mb-6">
                    <x-card.header>
                        <x-card.title class="flex items-center gap-2">
                            <x-lucide-twitter class="h-5 w-5 text-sky-500" />
                            Autenticación de Twitter
                        </x-card.title>
                        <x-card.description>Gestionar archivo de sesión de Twitter</x-card.description>
                    </x-card.header>
                    <x-card.content class="space-y-4">
                        @if($twitterAuthExists)
                            <!-- Auth file exists - show status and delete option -->
                            <div class="rounded-lg bg-green-50 border border-green-200 p-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-green-800 mb-1">✅ Sesión activa</p>
                                        <p class="text-xs text-green-700 mb-3">El archivo de autenticación está configurado. El Twitter Poster usará esta sesión automáticamente.</p>

                                        @if($twitterUsername && $twitterDisplayName &&
                                            $twitterUsername !== '@yourusername' &&
                                            $twitterUsername !== '@soyemizapata' &&
                                            $twitterDisplayName !== 'Your Display Name' &&
                                            $twitterDisplayName !== 'El Emiliano Zapata')
                                            <div class="mt-2 pt-2 border-t border-green-300">
                                                <p class="text-xs font-semibold text-green-800 mb-1">Perfil configurado:</p>
                                                <div class="text-xs text-green-700 space-y-0.5">
                                                    <p><strong>Nombre:</strong> {{ $twitterDisplayName }}</p>
                                                    <p><strong>Usuario:</strong> {{ $twitterUsername }}</p>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    <button
                                        wire:click="deleteTwitterAuth"
                                        wire:confirm="¿Estás seguro de eliminar la autenticación de Twitter?"
                                        class="text-red-600 hover:text-red-800 text-sm font-medium flex items-center gap-1 ml-4"
                                        wire:loading.attr="disabled"
                                        wire:target="deleteTwitterAuth"
                                    >
                                        <x-lucide-trash-2 class="h-4 w-4" />
                                        <span wire:loading.remove wire:target="deleteTwitterAuth">Eliminar</span>
                                        <span wire:loading wire:target="deleteTwitterAuth">Eliminando...</span>
                                    </button>
                                </div>
                            </div>
                        @else
                            <!-- No auth file - show upload form -->
                            <div class="rounded-lg bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800">
                                <p class="font-semibold mb-2">📋 Instrucciones:</p>
                                <ol class="list-decimal list-inside space-y-1 ml-2">
                                    <li>Ejecuta <code class="bg-blue-200 px-1 rounded">capture_twitter_session.py</code> en tu PC</li>
                                    <li>Inicia sesión en Twitter cuando se abra el navegador</li>
                                    <li>El script generará <code class="bg-blue-200 px-1 rounded">auth_twitter.json</code></li>
                                    <li>Sube el archivo aquí</li>
                                </ol>
                            </div>

                            <form wire:submit.prevent="uploadTwitterAuth" class="space-y-4">
                                <div class="space-y-2">
                                    <x-label for="twitterAuthFile">
                                        Archivo de Sesión (auth_twitter.json)
                                        <span class="text-destructive">*</span>
                                    </x-label>
                                    <input
                                        type="file"
                                        id="twitterAuthFile"
                                        wire:model="twitterAuthFile"
                                        accept=".json"
                                        class="block w-full text-sm text-slate-500
                                            file:mr-4 file:py-2 file:px-4
                                            file:rounded-md file:border-0
                                            file:text-sm file:font-semibold
                                            file:bg-blue-50 file:text-blue-700
                                            hover:file:bg-blue-100
                                            cursor-pointer"
                                    />
                                    @error('twitterAuthFile') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                                    <div wire:loading wire:target="twitterAuthFile" class="text-xs text-muted-foreground">
                                        Cargando archivo...
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <x-button
                                        type="submit"
                                        wire:loading.attr="disabled"
                                        wire:target="uploadTwitterAuth"
                                    >
                                        <x-lucide-upload class="mr-2 h-4 w-4" />
                                        <span wire:loading.remove wire:target="uploadTwitterAuth">Subir Archivo</span>
                                        <span wire:loading wire:target="uploadTwitterAuth">Subiendo...</span>
                                    </x-button>
                                </div>
                            </form>
                        @endif
                    </x-card.content>
                </x-card>

                <!-- Facebook Page Posting Settings -->
                <x-card class="mb-6">
                    <x-card.header>
                        <div class="flex items-center justify-between">
                            <div>
                                <x-card.title>Publicación en Página de Facebook</x-card.title>
                                <x-card.description>Configurar la publicación automática de imágenes aprobadas en tu página de Facebook</x-card.description>
                            </div>
                            <button type="button" wire:click="togglePagePosting"
                                    style="width: 60px; height: 34px; border-radius: 17px; position: relative; transition: all 0.3s; cursor: pointer; {{ $pagePostingEnabled ? 'background-color: #16a34a;' : 'background-color: #d1d5db;' }}"
                                    class="focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                    title="Click para {{ $pagePostingEnabled ? 'desactivar' : 'activar' }}">
                                <span style="position: absolute; top: 3px; {{ $pagePostingEnabled ? 'left: 28px;' : 'left: 3px;' }} width: 28px; height: 28px; background-color: white; border-radius: 50%; transition: all 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></span>
                            </button>
                        </div>
                    </x-card.header>
                    <x-card.content>
                        <form wire:submit.prevent="savePagePostingSettings">
                            <div class="space-y-6">
                                <!-- Page Name -->
                                <div>
                                    <label class="block text-sm font-medium text-foreground mb-2">
                                        Nombre de la Página <span class="text-destructive">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        wire:model="pageName"
                                        placeholder="Ej: Miltoner, Mi Página de Negocios"
                                        class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                    />
                                    @error('pageName') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                                    <p class="text-xs text-muted-foreground mt-1">
                                        El nombre exacto de tu página de Facebook (debe coincidir exactamente)
                                    </p>
                                </div>

                                <!-- Page URL -->
                                <div>
                                    <label class="block text-sm font-medium text-foreground mb-2">
                                        URL de la Página <span class="text-destructive">*</span>
                                    </label>
                                    <input
                                        type="url"
                                        wire:model="pageUrl"
                                        placeholder="https://www.facebook.com/TuPagina"
                                        class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                    />
                                    @error('pageUrl') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                                    <p class="text-xs text-muted-foreground mt-1">
                                        La URL completa de tu página de Facebook (se usa para validar que estás logueado correctamente)
                                    </p>
                                </div>

                                <!-- Posting Intervals -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-foreground mb-2">
                                            Intervalo Mínimo (minutos)
                                        </label>
                                        <input
                                            type="number"
                                            wire:model="pageIntervalMin"
                                            min="10"
                                            max="1440"
                                            class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                        />
                                        @error('pageIntervalMin') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-foreground mb-2">
                                            Intervalo Máximo (minutos)
                                        </label>
                                        <input
                                            type="number"
                                            wire:model="pageIntervalMax"
                                            min="10"
                                            max="1440"
                                            class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                        />
                                        @error('pageIntervalMax') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <!-- Auto-cleanup Settings -->
                                <div class="border-t border-border pt-6 mt-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <div>
                                            <h4 class="text-sm font-semibold text-foreground">Limpieza Automática de Imágenes Publicadas</h4>
                                            <p class="text-xs text-muted-foreground mt-1">Eliminar automáticamente imágenes descargadas y publicadas después de N días</p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            wire:model.live="autoCleanupEnabled"
                                            class="h-5 w-5 rounded text-primary focus:ring-primary cursor-pointer"
                                        />
                                    </div>

                                    @if ($autoCleanupEnabled)
                                        <div>
                                            <label class="block text-sm font-medium text-foreground mb-2">
                                                Días para mantener imágenes <span class="text-destructive">*</span>
                                            </label>
                                            <input
                                                type="number"
                                                wire:model="cleanupDays"
                                                min="1"
                                                max="365"
                                                class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                            />
                                            @error('cleanupDays') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                                            <p class="text-xs text-muted-foreground mt-1">
                                                Las imágenes descargadas y publicadas hace más de {{ $cleanupDays }} días serán eliminadas automáticamente
                                            </p>
                                        </div>
                                    @endif
                                </div>

                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div class="flex items-start gap-3">
                                        <x-lucide-info class="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" />
                                        <div class="text-sm text-blue-800">
                                            <p class="font-medium mb-1">Cómo funciona:</p>
                                            <ul class="list-disc list-inside space-y-1 text-xs">
                                                <li>Las imágenes deben ser aprobadas manualmente en la sección "Aprobación"</li>
                                                <li>El sistema publicará una imagen cada vez con un intervalo aleatorio entre el mínimo y máximo</li>
                                                <li>Solo se publican imágenes (sin texto)</li>
                                                <li>El cronjob se ejecuta cada 30 minutos para verificar si es tiempo de publicar</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="flex justify-end">
                                    <x-button type="submit">
                                        <x-lucide-save class="mr-2 h-4 w-4" />
                                        Guardar Configuración
                                    </x-button>
                                </div>
                            </div>
                        </form>
                    </x-card.content>
                </x-card>
        </div>
