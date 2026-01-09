<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Am?l.ia
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div id="chat-theme" class="chat-theme p-6 text-gray-900 dark:text-gray-100" data-theme="">
                    <div class="mb-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Selecione o setor da solicitacao
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" class="chat-sector-btn" data-sector="juridico" aria-pressed="false">
                                Juridico
                            </button>
                            <button type="button" class="chat-sector-btn" data-sector="mkt" aria-pressed="false">
                                MKT
                            </button>
                            <button type="button" class="chat-sector-btn" data-sector="rh" aria-pressed="false">
                                RH
                            </button>
                        </div>
                        <div id="chat-sector-hint" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Escolha um setor antes de enviar sua mensagem.
                        </div>
                    </div>

                    <div id="chat-panel" class="hidden">
                        <div id="chat-log" class="chat-log space-y-4 min-h-[60vh] lg:min-h-[70vh] border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-900">
                            <div id="chat-empty" class="text-sm text-gray-500 dark:text-gray-400">
                                Seja bem-vindo ao chatbot do GRUPO W3. Me chamo Am?lia e estou disposta a te ajudar com suas demandas.
                            </div>
                        </div>

                        <form id="chat-form" class="mt-4 flex gap-2">
                            <input id="chat-input" type="text" autocomplete="off" placeholder="Digite sua mensagem" class="chat-input flex-1 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            <button id="chat-send" type="submit" class="px-4 py-2 rounded-md btn-accent disabled:opacity-50">
                                Enviar
                            </button>
                        </form>

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Dica: seja especifico para respostas melhores.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const form = document.getElementById('chat-form');
            const input = document.getElementById('chat-input');
            const chatPanel = document.getElementById('chat-panel');
            const log = document.getElementById('chat-log');
            const empty = document.getElementById('chat-empty');
            const send = document.getElementById('chat-send');
            const chatTheme = document.getElementById('chat-theme');
            const sectorHint = document.getElementById('chat-sector-hint');
            const sectorButtons = document.querySelectorAll('.chat-sector-btn');

            let selectedSector = '';
            let isPending = false;
            let lockSector = false;

            const updateFormState = () => {
                const canSend = selectedSector !== '' && !isPending;
                input.disabled = !canSend;
                send.disabled = !canSend;
                input.placeholder = selectedSector ? 'Digite sua mensagem' : 'Selecione um setor acima';
            };

            const updatePanelVisibility = () => {
                if (chatPanel) {
                    chatPanel.classList.toggle('hidden', selectedSector === '');
                }
            };

            const updateSectorUI = () => {
                sectorButtons.forEach((button) => {
                    const isActive = button.dataset.sector === selectedSector;
                    button.classList.toggle('chat-sector-btn-active', isActive);
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    if (lockSector) {
                        button.disabled = true;
                        button.classList.add('opacity-60', 'cursor-not-allowed');
                    }
                });

                if (sectorHint) {
                    sectorHint.textContent = selectedSector
                        ? `Setor selecionado: ${selectedSector.toUpperCase()}`
                        : 'Escolha um setor antes de enviar sua mensagem.';
                }
            };

            const setSector = (sector) => {
                if (lockSector) {
                    return;
                }
                selectedSector = sector;
                if (chatTheme) {
                    chatTheme.dataset.theme = sector;
                }
                updateSectorUI();
                updateFormState();
                updatePanelVisibility();
            };

            sectorButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const sector = button.dataset.sector || '';
                    if (sector !== '') {
                        setSector(sector);
                    }
                });
            });

            const removeEmpty = () => {
                if (empty) {
                    empty.remove();
                }
            };

            const appendMessage = (role, text) => {
                removeEmpty();
                const wrapper = document.createElement('div');
                wrapper.className = role === 'user' ? 'flex justify-end' : 'flex justify-start';

                const bubble = document.createElement('div');
                bubble.className = role === 'user'
                    ? 'chat-bubble chat-bubble-user'
                    : 'chat-bubble chat-bubble-assistant';
                bubble.textContent = text;

                wrapper.appendChild(bubble);
                log.appendChild(wrapper);
                log.scrollTop = log.scrollHeight;

                if (!lockSector) {
                    lockSector = true;
                    updateSectorUI();
                }
            };

            let pendingEl = null;
            const setPending = (on) => {
                isPending = on;
                updateFormState();
                if (on) {
                    if (!pendingEl) {
                        pendingEl = document.createElement('div');
                        pendingEl.className = 'flex justify-start';
                        pendingEl.innerHTML = '<div class="chat-bubble chat-bubble-assistant">Digitando...</div>';
                        log.appendChild(pendingEl);
                        log.scrollTop = log.scrollHeight;
                    }
                } else if (pendingEl) {
                    pendingEl.remove();
                    pendingEl = null;
                }
            };

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!selectedSector) {
                    if (sectorHint) {
                        sectorHint.textContent = 'Escolha um setor para continuar.';
                    }
                    return;
                }
                const text = input.value.trim();
                if (!text) {
                    return;
                }

                appendMessage('user', text);
                input.value = '';
                input.focus();
                setPending(true);

                try {
                    const response = await window.axios.post('/chat', { message: text, sector: selectedSector });
                    const reply = response?.data?.text || 'Sem resposta.';
                    appendMessage('assistant', reply);
                } catch (error) {
                    const message = error?.response?.data?.error || error?.message || 'Erro ao chamar a API.';
                    appendMessage('assistant', message);
                } finally {
                    setPending(false);
                }
            });

            updateFormState();
            updateSectorUI();
            updatePanelVisibility();
        })();
    </script>
</x-app-layout>
