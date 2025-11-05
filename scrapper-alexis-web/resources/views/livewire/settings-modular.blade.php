<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
     x-data="{
        activeModal: null,
        openModal(name) {
            this.activeModal = name;
            console.log('Opening modal:', name);
        },
        closeModal() {
            this.activeModal = null;
        }
    }">

    <!-- Settings Overview Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

        <!-- Facebook Account -->
        <x-card class="hover:shadow-lg transition-shadow cursor-pointer" @click="openModal('facebook')">
            <x-card.content class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-foreground flex items-center gap-2">
                            <x-lucide-facebook class="h-5 w-5 text-blue-600" />
                            Cuenta de Facebook
                            @if($facebookEmail && $facebookPassword && count($facebookProfilesList) > 0 && $facebookAuthExists)
                                <x-lucide-check-circle class="h-5 w-5 text-green-600" />
                            @endif
                        </h3>
                        <p class="text-sm text-muted-foreground mt-1">Credenciales y sesión</p>
                    </div>
                    <x-lucide-chevron-right class="h-5 w-5 text-muted-foreground" />
                </div>
            </x-card.content>
        </x-card>

        <!-- Página donde se publica -->
        <x-card class="hover:shadow-lg transition-shadow cursor-pointer" @click="openModal('page-posting')">
            <x-card.content class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-foreground flex items-center gap-2">
                            <x-lucide-share-2 class="h-5 w-5 text-indigo-600" />
                            Página donde se publica
                            @if($pageName)
                                <x-lucide-check-circle class="h-5 w-5 text-green-600" />
                            @endif
                        </h3>
                        <p class="text-sm text-muted-foreground mt-1">Configuración y auto-limpieza</p>
                    </div>
                    <x-lucide-chevron-right class="h-5 w-5 text-muted-foreground" />
                </div>
            </x-card.content>
        </x-card>

        <!-- Image Generator Configuration -->
        <x-card class="hover:shadow-lg transition-shadow cursor-pointer" @click="openModal('image-generator')">
            <x-card.content class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-foreground flex items-center gap-2">
                            <x-lucide-image class="h-5 w-5 text-purple-600" />
                            Generador de Imágenes
                            @if($twitterDisplayName && $twitterUsername)
                                <x-lucide-check-circle class="h-5 w-5 text-green-600" />
                            @endif
                        </h3>
                        <p class="text-sm text-muted-foreground mt-1">Perfil y avatar para imágenes</p>
                    </div>
                    <x-lucide-chevron-right class="h-5 w-5 text-muted-foreground" />
                </div>
            </x-card.content>
        </x-card>

        <!-- Proxy Config -->
        <x-card class="hover:shadow-lg transition-shadow cursor-pointer" @click="openModal('proxy')">
            <x-card.content class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-foreground flex items-center gap-2">
                            <x-lucide-globe class="h-5 w-5 text-purple-600" />
                            Configuración Proxy
                            @if($proxyServer)
                                <x-lucide-check-circle class="h-5 w-5 text-green-600" />
                            @endif
                        </h3>
                        <p class="text-sm text-muted-foreground mt-1">Servidor proxy</p>
                    </div>
                    <x-lucide-chevron-right class="h-5 w-5 text-muted-foreground" />
                </div>
            </x-card.content>
        </x-card>

        <!-- Cron Scheduling -->
        <x-card class="hover:shadow-lg transition-shadow cursor-pointer" @click="openModal('cron-schedule')">
            <x-card.content class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-foreground flex items-center gap-2">
                            <x-lucide-clock class="h-5 w-5 text-orange-600" />
                            Programación Cron
                        </h3>
                        <p class="text-sm text-muted-foreground mt-1">Intervalos de ejecución</p>
                    </div>
                    <x-lucide-chevron-right class="h-5 w-5 text-muted-foreground" />
                </div>
            </x-card.content>
        </x-card>

    </div>


    <!-- Page Posting Modal -->
    <template x-teleport="body">
        <div x-show="activeModal === 'page-posting'"
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
             style="display: none;"
             @click="closeModal()">
            <div class="bg-white rounded-lg shadow-2xl p-8 max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto"
                 @click.stop
                 x-transition.scale.80>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-foreground">Página donde se publica</h2>
                    <button @click="closeModal()" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="h-6 w-6" />
                    </button>
                </div>

                <form wire:submit.prevent="savePagePostingSettings">
                    <div class="space-y-6">
                        <!-- Enable Toggle -->
                        <div class="flex items-center justify-between p-4 bg-accent/50 rounded-lg">
                            <div>
                                <h4 class="font-semibold text-foreground">Estado</h4>
                                <p class="text-sm text-muted-foreground">{{ $pagePostingEnabled ? 'Activo' : 'Desactivado' }}</p>
                            </div>
                            <button type="button" wire:click="togglePagePosting"
                                    style="width: 60px; height: 34px; border-radius: 17px; position: relative; transition: all 0.3s; cursor: pointer; {{ $pagePostingEnabled ? 'background-color: #16a34a;' : 'background-color: #d1d5db;' }}"
                                    class="focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                <span style="position: absolute; top: 3px; {{ $pagePostingEnabled ? 'left: 28px;' : 'left: 3px;' }} width: 28px; height: 28px; background-color: white; border-radius: 50%; transition: all 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></span>
                            </button>
                        </div>

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
                            <p class="text-xs text-muted-foreground mt-1">La URL completa de tu página de Facebook (se usa para validar que estás logueado correctamente)</p>
                        </div>

                        <!-- Intervals -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-foreground mb-2">Mínimo (minutos)</label>
                                <input type="number" wire:model="pageIntervalMin" min="1" max="1440" class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-foreground mb-2">Máximo (minutos)</label>
                                <input type="number" wire:model="pageIntervalMax" min="1" max="1440" class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" />
                            </div>
                        </div>

                        <!-- Page Posting Debug Toggle -->
                        <div class="border-t border-border pt-6">
                            <div class="flex items-center justify-between p-4 bg-accent/50 rounded-lg">
                                <div>
                                    <h4 class="font-semibold text-foreground flex items-center gap-2">
                                        <x-lucide-bug class="h-5 w-5 text-orange-600" />
                                        Debug Output
                                    </h4>
                                    <p class="text-sm text-muted-foreground mt-1">{{ $pagePostingDebugEnabled ? 'Activado - Screenshots y logs habilitados' : 'Desactivado - Sin screenshots' }}</p>
                                </div>
                                <button type="button" wire:click="togglePagePostingDebug"
                                        style="width: 60px; height: 34px; border-radius: 17px; position: relative; transition: all 0.3s; cursor: pointer; {{ $pagePostingDebugEnabled ? 'background-color: #16a34a;' : 'background-color: #d1d5db;' }}"
                                        class="focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    <span style="position: absolute; top: 3px; {{ $pagePostingDebugEnabled ? 'left: 28px;' : 'left: 3px;' }} width: 28px; height: 28px; background-color: white; border-radius: 50%; transition: all 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Auto-cleanup Section -->
                        <div class="border-t border-border pt-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-foreground">Limpieza Automática</h4>
                                    <p class="text-xs text-muted-foreground mt-1">Eliminar imágenes descargadas y publicadas automáticamente</p>
                                </div>
                                <input type="checkbox" wire:model.live="autoCleanupEnabled" class="h-5 w-5 rounded text-primary focus:ring-primary cursor-pointer" />
                            </div>

                            @if ($autoCleanupEnabled)
                                <div>
                                    <label class="block text-sm font-medium text-foreground mb-2">Días para mantener imágenes</label>
                                    <input type="number" wire:model="cleanupDays" min="1" max="365" class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" />
                                    @error('cleanupDays') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                                    <p class="text-xs text-muted-foreground mt-1">Las imágenes descargadas y publicadas hace más de {{ $cleanupDays }} días serán eliminadas</p>
                                </div>
                            @endif
                        </div>

                        <!-- Save Button -->
                        <div class="flex justify-end gap-3 pt-4">
                            <x-button type="button" variant="outline" @click="closeModal()">Cancelar</x-button>
                            <x-button type="submit" wire:loading.attr="disabled" wire:target="savePagePostingSettings">
                                <x-lucide-save class="mr-2 h-4 w-4" />
                                Guardar Configuración
                            </x-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Cron Schedule Modal (Merged with Cronjobs) -->
    <template x-teleport="body">
        <div x-show="activeModal === 'cron-schedule' || activeModal === 'cronjobs'"
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
             style="display: none;"
             @click="closeModal()">
            <div class="bg-white rounded-lg shadow-2xl p-8 max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto"
                 @click.stop
                 x-transition.scale.80>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-foreground">Programación Cron y Cronjobs</h2>
                    <button @click="closeModal()" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="h-6 w-6" />
                    </button>
                </div>

                <form wire:submit.prevent="saveCronSettings">
                    <div class="space-y-6">
                        <!-- Facebook Scraper Cronjob Toggle -->
                        <x-card>
                            <x-card.content class="p-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <x-lucide-facebook class="h-5 w-5 text-blue-600" />
                                            <h4 class="font-semibold text-foreground">Facebook Scraper</h4>
                                        </div>
                                        <p class="text-sm text-muted-foreground">Estado: @if($facebookEnabled) 🟢 Activo @else 🔴 Detenido @endif</p>
                                        <p class="text-xs text-muted-foreground mt-1">{{ $facebookEnabled ? 'El cronjob está ejecutándose' : 'El cronjob está deshabilitado' }}</p>
                                    </div>
                                    <button type="button" wire:click="toggleFacebook"
                                            style="width: 60px; height: 34px; border-radius: 17px; position: relative; transition: all 0.3s; cursor: pointer; {{ $facebookEnabled ? 'background-color: #16a34a;' : 'background-color: #d1d5db;' }}"
                                            class="focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        <span style="position: absolute; top: 3px; {{ $facebookEnabled ? 'left: 28px;' : 'left: 3px;' }} width: 28px; height: 28px; background-color: white; border-radius: 50%; transition: all 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></span>
                                    </button>
                                </div>
                            </x-card.content>
                        </x-card>

                        <!-- Facebook Interval -->
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-2">Intervalo Facebook (minutos)</label>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs text-muted-foreground">Mínimo</label>
                                    <input type="number" wire:model="facebookIntervalMin" min="1" max="1440" class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" />
                                </div>
                                <div>
                                    <label class="text-xs text-muted-foreground">Máximo</label>
                                    <input type="number" wire:model="facebookIntervalMax" min="1" max="1440" class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" />
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="flex justify-end gap-3 pt-4">
                            <x-button type="button" variant="outline" @click="closeModal()">Cancelar</x-button>
                            <x-button type="submit" wire:loading.attr="disabled" wire:target="saveCronSettings">
                                <x-lucide-save class="mr-2 h-4 w-4" />
                                Guardar Configuración
                            </x-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Facebook Account Modal -->
    <template x-teleport="body">
        <div x-show="activeModal === 'facebook'"
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
             style="display: none;"
             @click="closeModal()">
            <div class="bg-white rounded-lg shadow-2xl p-8 max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto"
                 @click.stop
                 x-transition.scale.80>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-foreground">Cuenta de Facebook</h2>
                    <button @click="closeModal()" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="h-6 w-6" />
                    </button>
                </div>

                <form wire:submit.prevent="saveFacebookSettings">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-2">Correo electrónico</label>
                            <input type="text" wire:model="facebookEmail" class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-foreground mb-2">Contraseña</label>
                            <input type="password" wire:model="facebookPassword" class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" />
                        </div>

                        <!-- Profile URLs List -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-medium text-foreground">URLs de perfiles</label>
                            </div>

                            <!-- Add URL Input -->
                            <div class="flex gap-2 mb-3">
                                <input
                                    type="url"
                                    wire:model="newProfileUrl"
                                    placeholder="https://facebook.com/perfil"
                                    class="flex-1 px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                />
                                <x-button type="button" wire:click="addProfileUrl" size="sm">
                                    <x-lucide-plus class="h-4 w-4" />
                                </x-button>
                            </div>
                            @error('newProfileUrl') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror

                            <!-- Profile URLs List -->
                            @if(count($facebookProfilesList) > 0)
                                <div class="space-y-2 max-h-40 overflow-y-auto">
                                    @foreach($facebookProfilesList as $index => $profileUrl)
                                        <div class="flex items-center gap-2 p-2 bg-accent/50 rounded-lg">
                                            <span class="flex-1 text-sm truncate">{{ $profileUrl }}</span>
                                            <button type="button" wire:click="removeProfileUrl({{ $index }})" class="text-destructive hover:text-destructive/80 cursor-pointer">
                                                <x-lucide-x class="h-4 w-4" />
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-muted-foreground">No hay URLs agregadas. Usa el botón + para agregar.</p>
                            @endif
                        </div>

                        <!-- Facebook Debug Toggle -->
                        <div class="border-t border-border pt-6">
                            <div class="flex items-center justify-between p-4 bg-accent/50 rounded-lg">
                                <div>
                                    <h4 class="font-semibold text-foreground flex items-center gap-2">
                                        <x-lucide-bug class="h-5 w-5 text-orange-600" />
                                        Debug Output
                                    </h4>
                                    <p class="text-sm text-muted-foreground mt-1">{{ $facebookDebugEnabled ? 'Activado - Screenshots y logs habilitados' : 'Desactivado - Sin screenshots' }}</p>
                                </div>
                                <button type="button" wire:click="toggleFacebookDebug"
                                        style="width: 60px; height: 34px; border-radius: 17px; position: relative; transition: all 0.3s; cursor: pointer; {{ $facebookDebugEnabled ? 'background-color: #16a34a;' : 'background-color: #d1d5db;' }}"
                                        class="focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    <span style="position: absolute; top: 3px; {{ $facebookDebugEnabled ? 'left: 28px;' : 'left: 3px;' }} width: 28px; height: 28px; background-color: white; border-radius: 50%; transition: all 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Facebook Auth Section -->
                        <div class="border-t border-border pt-6">
                            <h4 class="font-semibold text-foreground mb-4 flex items-center gap-2">
                                <x-lucide-shield-check class="h-5 w-5 text-green-600" />
                                Autenticación de Sesión
                            </h4>

                            @if($facebookAuthExists)
                                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                                    <p class="text-sm text-green-800 font-semibold">✓ Sesión activa</p>
                                </div>
                                <x-button type="button" wire:click="deleteFacebookAuth" variant="destructive" class="w-full">
                                    <x-lucide-trash-2 class="mr-2 h-4 w-4" />
                                    Eliminar Autenticación
                                </x-button>
                            @else
                                <div class="space-y-4">
                                    <div class="rounded-lg bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800">
                                        <p class="font-semibold mb-2">📋 Instrucciones:</p>
                                        <ol class="list-decimal list-inside space-y-1 ml-2 text-xs">
                                            <li>Ejecuta <code class="bg-blue-200 px-1 rounded">capture_facebook_session.py</code> en tu PC</li>
                                            <li>Inicia sesión en Facebook cuando se abra el navegador</li>
                                            <li>El script generará <code class="bg-blue-200 px-1 rounded">auth_facebook.json</code></li>
                                            <li>Sube el archivo aquí</li>
                                        </ol>
                                    </div>

                                    <input type="file" wire:model="facebookAuthFile" accept=".json" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer" />
                                    @error('facebookAuthFile') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror

                                    @if($facebookAuthFile)
                                        <x-button type="button" wire:click="uploadFacebookAuth" class="w-full">
                                            <x-lucide-upload class="mr-2 h-4 w-4" />
                                            Subir Archivo
                                        </x-button>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Save Button -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-border">
                            <x-button type="button" variant="outline" @click="closeModal()">Cancelar</x-button>
                            <x-button type="submit" wire:loading.attr="disabled" wire:target="saveFacebookSettings">
                                <x-lucide-save class="mr-2 h-4 w-4" />
                                Guardar Configuración
                            </x-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Image Generator Configuration Modal -->
    <template x-teleport="body">
        <div x-show="activeModal === 'image-generator'"
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
             style="display: none;"
             @click="closeModal()">
            <div class="bg-white rounded-lg shadow-2xl p-8 max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto"
                 @click.stop
                 x-transition.scale.80>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-foreground">Generador de Imágenes</h2>
                    <button @click="closeModal()" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="h-6 w-6" />
                    </button>
                </div>

                <div class="mb-4 p-4 bg-purple-50 border border-purple-200 rounded-lg">
                    <p class="text-sm text-purple-800">
                        <strong>ℹ️ Información:</strong> Configura el perfil que aparecerá en las imágenes generadas. El avatar y datos se usarán en el template HTML.
                    </p>
                </div>

                <form wire:submit.prevent="saveImageGeneratorSettings">
                    <div class="space-y-6">
                        <!-- Display Name -->
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-2">
                                Nombre de Perfil <span class="text-destructive">*</span>
                            </label>
                            <input 
                                type="text" 
                                wire:model="twitterDisplayName" 
                                placeholder="Ej: Miltoner" 
                                class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" 
                                required
                            />
                            <p class="text-xs text-muted-foreground mt-1">El nombre que se muestra en la imagen generada</p>
                            @error('twitterDisplayName') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Username -->
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-2">
                                Nombre de Usuario <span class="text-destructive">*</span>
                            </label>
                            <input 
                                type="text" 
                                wire:model="twitterUsername" 
                                placeholder="Ej: @teammiltoner" 
                                class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" 
                                required
                            />
                            <p class="text-xs text-muted-foreground mt-1">El @usuario que aparece en la imagen (incluye el @)</p>
                            @error('twitterUsername') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Avatar Upload -->
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-2">
                                Avatar de Perfil
                            </label>
                            
                            @if($twitterAvatarUrl)
                                <div class="mb-3 p-3 bg-green-50 border border-green-200 rounded-lg flex items-center gap-3">
                                    <img src="{{ Storage::url($twitterAvatarUrl) }}" alt="Avatar actual" class="w-12 h-12 rounded-full object-cover" />
                                    <div class="flex-1">
                                        <p class="text-sm text-green-800 font-semibold">✓ Avatar cargado</p>
                                        <p class="text-xs text-green-700">Sube uno nuevo para reemplazarlo</p>
                                    </div>
                                </div>
                            @endif

                            <input 
                                type="file" 
                                wire:model="avatarUpload" 
                                accept="image/jpeg,image/jpg,image/png" 
                                class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 cursor-pointer" 
                            />
                            <p class="text-xs text-muted-foreground mt-1">JPG, JPEG o PNG. Máximo 2MB. Recomendado: 400x400px</p>
                            @error('avatarUpload') <p class="text-destructive text-xs mt-1">{{ $message }}</p> @enderror

                            @if($avatarUpload)
                                <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <p class="text-sm text-blue-800">📤 Nuevo avatar seleccionado. Guarda para aplicar cambios.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Verified Badge Toggle -->
                        <div class="border border-border rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <svg viewBox="0 0 22 22" aria-label="Cuenta verificada" role="img" class="h-6 w-6 text-sky-500" fill="currentColor">
                                        <g><path d="M20.396 11c-.018-.646-.215-1.275-.57-1.816-.354-.54-.852-.972-1.438-1.246.223-.607.27-1.264.14-1.897-.131-.634-.437-1.218-.882-1.687-.47-.445-1.053-.75-1.687-.882-.633-.13-1.29-.083-1.897.14-.273-.587-.704-1.086-1.245-1.44S11.647 1.62 11 1.604c-.646.017-1.273.213-1.813.568s-.969.854-1.24 1.44c-.608-.223-1.267-.272-1.902-.14-.635.13-1.22.436-1.69.882-.445.47-.749 1.055-.878 1.688-.13.633-.08 1.29.144 1.896-.587.274-1.087.705-1.443 1.245-.356.54-.555 1.17-.574 1.817.02.647.218 1.276.574 1.817.356.54.856.972 1.443 1.245-.224.606-.274 1.263-.144 1.896.13.634.433 1.218.877 1.688.47.443 1.054.747 1.687.878.633.132 1.29.084 1.897-.136.274.586.705 1.084 1.246 1.439.54.354 1.17.551 1.816.569.647-.016 1.276-.213 1.817-.567s.972-.854 1.245-1.44c.604.239 1.266.296 1.903.164.636-.132 1.22-.447 1.68-.907.46-.46.776-1.044.908-1.681s.075-1.299-.165-1.903c.586-.274 1.084-.705 1.439-1.246.354-.54.551-1.17.569-1.816zM9.662 14.85l-3.429-3.428 1.293-1.302 2.072 2.072 4.4-4.794 1.347 1.246z"></path></g>
                                    </svg>
                                    <div>
                                        <label class="block text-sm font-medium text-foreground">Badge de Verificación</label>
                                        <p class="text-xs text-muted-foreground mt-1">Mostrar el badge azul de verificación en las imágenes</p>
                                    </div>
                                </div>
                                <input type="checkbox" wire:model.live="twitterVerified" class="h-5 w-5 rounded text-primary focus:ring-primary cursor-pointer" />
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-border">
                            <x-button type="button" variant="outline" @click="closeModal()">Cancelar</x-button>
                            <x-button type="submit" wire:loading.attr="disabled" wire:target="saveImageGeneratorSettings">
                                <x-lucide-save class="mr-2 h-4 w-4" />
                                Guardar Configuración
                            </x-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Proxy Config Modal -->
    <template x-teleport="body">
        <div x-show="activeModal === 'proxy'"
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
             style="display: none;"
             @click="closeModal()">
            <div class="bg-white rounded-lg shadow-2xl p-8 max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto"
                 @click.stop
                 x-transition.scale.80>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-foreground">Configuración Proxy</h2>
                    <button @click="closeModal()" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="h-6 w-6" />
                    </button>
                </div>

                <form wire:submit.prevent="saveProxySettings">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-2">Servidor Proxy</label>
                            <input type="text" wire:model="proxyServer" placeholder="http://proxy.example.com:8080" class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" />
                            <p class="text-xs text-muted-foreground mt-1">Deja en blanco para no usar proxy</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-foreground mb-2">Usuario Proxy</label>
                            <input type="text" wire:model="proxyUsername" class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-foreground mb-2">Contraseña Proxy</label>
                            <input type="password" wire:model="proxyPassword" class="w-full px-4 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" />
                        </div>

                        <!-- Test Proxy Section -->
                        <div class="pt-4 border-t border-border" 
                             x-data="{ 
                                 testResult: null, 
                                 showResult: false 
                             }"
                             @proxy-test-result.window="
                                 testResult = $event.detail; 
                                 showResult = true;
                                 setTimeout(() => { showResult = false; testResult = null; }, 8000);
                             ">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h3 class="text-sm font-medium text-foreground">Probar Conexión</h3>
                                    <p class="text-xs text-muted-foreground mt-1">Verificar que el proxy funciona correctamente</p>
                                </div>
                                <button type="button" 
                                        wire:click="testProxy" 
                                        wire:loading.attr="disabled"
                                        wire:target="testProxy"
                                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                                    <x-lucide-globe class="h-4 w-4" wire:loading.remove wire:target="testProxy" />
                                    <svg wire:loading wire:target="testProxy" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="testProxy">Probar</span>
                                    <span wire:loading wire:target="testProxy">Probando...</span>
                                </button>
                            </div>

                            <!-- Test Results -->
                            <div x-show="showResult" 
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 transform scale-95"
                                 x-transition:enter-end="opacity-100 transform scale-100"
                                 x-transition:leave="transition ease-in duration-200"
                                 x-transition:leave-start="opacity-100 transform scale-100"
                                 x-transition:leave-end="opacity-0 transform scale-95"
                                 class="mt-3 p-4 rounded-lg"
                                 :class="testResult?.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'"
                                 style="display: none;">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 mt-0.5">
                                        <x-lucide-check-circle x-show="testResult?.success" class="h-5 w-5 text-green-600" />
                                        <x-lucide-x-circle x-show="!testResult?.success" class="h-5 w-5 text-red-600" />
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium" :class="testResult?.success ? 'text-green-900' : 'text-red-900'" x-text="testResult?.message"></p>
                                        <div x-show="testResult?.details" class="mt-2 text-xs" :class="testResult?.success ? 'text-green-700' : 'text-red-700'">
                                            <div x-show="testResult?.details?.ip" class="flex items-center gap-2">
                                                <span class="font-semibold">IP del Proxy:</span>
                                                <span x-text="testResult?.details?.ip"></span>
                                            </div>
                                            <div x-show="testResult?.details?.response_time" class="flex items-center gap-2">
                                                <span class="font-semibold">Tiempo de respuesta:</span>
                                                <span x-text="testResult?.details?.response_time"></span>
                                            </div>
                                            <div x-show="testResult?.details?.error" class="flex items-center gap-2">
                                                <span class="font-semibold">Error:</span>
                                                <span x-text="testResult?.details?.error"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" @click="showResult = false; testResult = null;" class="flex-shrink-0 text-gray-400 hover:text-gray-600">
                                        <x-lucide-x class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-border">
                            <x-button type="button" variant="outline" @click="closeModal()">Cancelar</x-button>
                            <x-button type="submit" wire:loading.attr="disabled" wire:target="saveProxySettings">
                                <x-lucide-save class="mr-2 h-4 w-4" />
                                Guardar Configuración
                            </x-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Loading Modal -->
    <template x-teleport="body">
        <div wire:loading.flex
             wire:target="savePagePostingSettings, saveCronSettings, saveFacebookSettings, saveImageGeneratorSettings, saveProxySettings"
             class="fixed inset-0 z-50 items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="bg-white rounded-lg shadow-2xl p-8 flex flex-col items-center space-y-4 max-w-sm mx-4">
                <div class="relative">
                    <svg class="animate-spin h-16 w-16 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <p class="text-xl font-semibold text-gray-900">Guardando configuración...</p>
                <p class="text-sm text-gray-500">Por favor espera un momento</p>
            </div>
        </div>
    </template>

</div>

