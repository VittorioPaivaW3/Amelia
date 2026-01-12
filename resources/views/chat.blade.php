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
                    <div id="chat-panel">
                        <div id="chat-log" class="chat-log space-y-4 min-h-[60vh] lg:min-h-[70vh] border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-900">
                            <div class="flex justify-start">
                                <div class="chat-sector-card">
                                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                        Antes de comecar, qual setor da solicitacao?
                                    </div>
                                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
                                        <button type="button" class="chat-sector-btn w-full" data-sector="juridico" aria-pressed="false">
                                            Juridico
                                        </button>
                                        <button type="button" class="chat-sector-btn w-full" data-sector="mkt" aria-pressed="false">
                                            MKT
                                        </button>
                                        <button type="button" class="chat-sector-btn w-full" data-sector="rh" aria-pressed="false">
                                            RH
                                        </button>
                                    </div>
                                    <div id="chat-sector-hint" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Escolha um setor para continuar.
                                    </div>
                                </div>
                            </div>
                            <div id="chat-empty" class="flex justify-start">
                                <div class="chat-bubble chat-bubble-system max-w-lg">
                                    Seja bem-vindo ao chatbot do GRUPO W3. Me chamo Am?lia e estou disposta a te ajudar com suas demandas.
                                </div>
                            </div>
                        </div>

                        <form id="chat-form" class="mt-4 space-y-2">
                            <div class="flex gap-2">
                                <input id="chat-attachments" type="file" class="hidden" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                                <button id="chat-attach-btn" type="button" class="chat-attachment-btn" title="Anexar arquivos" aria-label="Anexar arquivos">
                                    <svg viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M6 6.5a4 4 0 0 1 8 0v6.8a2.8 2.8 0 1 1-5.6 0V7.2a1.4 1.4 0 0 1 2.8 0v5.5a.7.7 0 1 0 1.4 0V7.2a2.8 2.8 0 0 0-5.6 0v6.1a4.2 4.2 0 1 0 8.4 0V6.5a5.4 5.4 0 0 0-10.8 0v7.6"></path>
                                    </svg>
                                </button>
                                <input id="chat-input" type="text" autocomplete="off" placeholder="Digite sua mensagem" class="chat-input flex-1 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                <button id="chat-send" type="submit" class="px-4 py-2 rounded-md btn-accent disabled:opacity-50">
                                    Enviar
                                </button>
                            </div>
                            <div id="chat-attachments-list" class="chat-attachments hidden"></div>
                        </form>

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Dica: seja especifico para respostas melhores. Anexos: ate 5 arquivos de 10MB.
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
            const attachBtn = document.getElementById('chat-attach-btn');
            const attachmentsInput = document.getElementById('chat-attachments');
            const attachmentsList = document.getElementById('chat-attachments-list');
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
                if (attachBtn) {
                    attachBtn.disabled = !canSend;
                }
                if (attachmentsInput) {
                    attachmentsInput.disabled = !canSend;
                }
                input.placeholder = selectedSector ? 'Digite sua mensagem' : 'Selecione um setor para continuar';
            };

            const updatePanelVisibility = () => {
                if (chatPanel) {
                    chatPanel.classList.remove('hidden');
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

            const appendMessage = (role, text, attachments = []) => {
                removeEmpty();
                const wrapper = document.createElement('div');
                wrapper.className = role === 'user' ? 'flex justify-end' : 'flex justify-start';

                const bubble = document.createElement('div');
                bubble.className = role === 'user'
                    ? 'chat-bubble chat-bubble-user'
                    : 'chat-bubble chat-bubble-assistant';
                bubble.textContent = text;

                wrapper.appendChild(bubble);
                if (attachments.length) {
                    const attachmentWrap = document.createElement('div');
                    attachmentWrap.className = 'chat-attachments chat-attachments--inline';
                    attachments.forEach((file) => {
                        const item = document.createElement('div');
                        item.className = 'chat-attachment-item';
                        item.textContent = file.name;
                        attachmentWrap.appendChild(item);
                    });
                    wrapper.appendChild(attachmentWrap);
                }
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

            const pendingFiles = [];

            const formatSize = (size) => {
                if (!size && size !== 0) {
                    return '';
                }
                if (size < 1024) {
                    return `${size} B`;
                }
                const kb = size / 1024;
                if (kb < 1024) {
                    return `${Math.round(kb)} KB`;
                }
                const mb = kb / 1024;
                return `${mb.toFixed(1)} MB`;
            };

            const renderAttachments = () => {
                if (!attachmentsList) {
                    return;
                }
                attachmentsList.innerHTML = '';
                if (!pendingFiles.length) {
                    attachmentsList.classList.add('hidden');
                    return;
                }
                attachmentsList.classList.remove('hidden');
                pendingFiles.forEach((file, index) => {
                    const item = document.createElement('div');
                    item.className = 'chat-attachment-item';

                    const name = document.createElement('span');
                    name.className = 'chat-attachment-name';
                    name.textContent = file.name;

                    const meta = document.createElement('span');
                    meta.className = 'chat-attachment-meta';
                    meta.textContent = formatSize(file.size);

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'chat-attachment-remove';
                    remove.textContent = 'x';
                    remove.addEventListener('click', () => {
                        pendingFiles.splice(index, 1);
                        renderAttachments();
                    });

                    item.appendChild(name);
                    item.appendChild(meta);
                    item.appendChild(remove);
                    attachmentsList.appendChild(item);
                });

                if (attachmentsInput) {
                    const data = new DataTransfer();
                    pendingFiles.forEach((file) => data.items.add(file));
                    attachmentsInput.files = data.files;
                }
            };

            if (attachBtn && attachmentsInput) {
                attachBtn.addEventListener('click', () => {
                    if (!attachBtn.disabled) {
                        attachmentsInput.click();
                    }
                });

                attachmentsInput.addEventListener('change', () => {
                    const files = Array.from(attachmentsInput.files || []);
                    files.forEach((file) => {
                        if (pendingFiles.length < 5) {
                            pendingFiles.push(file);
                        }
                    });
                    renderAttachments();
                    attachmentsInput.value = '';
                });
            }

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

                appendMessage('user', text, pendingFiles.slice());
                input.value = '';
                input.focus();
                setPending(true);

                try {
                    const messageId = (window.crypto && window.crypto.randomUUID)
                        ? window.crypto.randomUUID()
                        : `msg-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
                    const formData = new FormData();
                    formData.append('message', text);
                    formData.append('sector', selectedSector);
                    formData.append('message_id', messageId);
                    pendingFiles.forEach((file) => {
                        formData.append('attachments[]', file);
                    });
                    const response = await window.axios.post('/chat', formData, {
                        headers: { 'Content-Type': 'multipart/form-data' },
                    });
                    const reply = response?.data?.text || 'Sem resposta.';
                    appendMessage('assistant', reply);
                    pendingFiles.splice(0, pendingFiles.length);
                    renderAttachments();
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
