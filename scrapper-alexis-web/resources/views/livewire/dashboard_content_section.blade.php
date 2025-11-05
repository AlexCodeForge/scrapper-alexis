                    @if($postedMessagesFiltered->count() > 0)
                        <!-- Messages Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b border-border bg-accent/50">
                                        <th class="text-left p-3 text-sm font-semibold text-foreground">Mensaje</th>
                                        <th class="text-left p-3 text-sm font-semibold text-foreground">Perfil</th>
                                        <th class="text-left p-3 text-sm font-semibold text-foreground">Fecha de Publicación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($postedMessagesFiltered as $message)
                                        <tr class="border-b border-border hover:bg-accent/30 transition-colors">
                                            <td class="p-3 text-sm text-foreground">
                                                <div class="max-w-2xl">
                                                    {{ Str::limit($message->message_text, 150) }}
                                                </div>
                                            </td>
                                            <td class="p-3 text-sm text-foreground whitespace-nowrap">
                                                @if($message->profile)
                                                    <div class="flex items-center gap-2">
                                                        <x-lucide-user class="h-4 w-4 text-muted-foreground" />
                                                        <span>{{ $message->profile->name }}</span>
                                                    </div>
                                                @else
                                                    <span class="text-muted-foreground">-</span>
                                                @endif
                                            </td>
                                            <td class="p-3 text-sm text-foreground whitespace-nowrap">
                                                <div class="flex flex-col">
                                                    <span class="font-medium">{{ $message->posted_to_page_at->format('d/m/Y') }}</span>
                                                    <span class="text-xs text-muted-foreground">{{ $message->posted_to_page_at->format('H:i') }}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-6">
                            {{ $postedMessagesFiltered->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <x-lucide-calendar-x class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                            <p class="text-base text-muted-foreground">No hay contenido publicado en este período</p>
                        </div>
                    @endif

