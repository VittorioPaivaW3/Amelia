<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    @php
        $role = $role ?? auth()->user()?->role;
        $isAdmin = $role === 'admin';
        $isStaff = in_array($role, ['mkt', 'juridico', 'rh'], true);
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if ($isAdmin || $isStaff)
                <div class="admin-dashboard">
                    <div class="admin-dashboard__canvas">
                        <div class="admin-filter-row">
                            <div>
                                <div class="admin-filter-title">Filtro de datas</div>
                                <div class="admin-filter-sub">Selecione o periodo para os indicadores</div>
                            </div>
                            <form method="GET" action="{{ route('dashboard') }}" class="admin-filter-form">
                                <div class="admin-calendar admin-calendar-control" data-calendar data-field="from" data-value="{{ $filterFrom ?? '' }}">
                                    <input type="hidden" name="from" data-calendar-input value="{{ $filterFrom ?? '' }}">
                                    <button type="button" class="admin-calendar-trigger" data-calendar-trigger aria-haspopup="dialog" aria-expanded="false">
                                        <span class="admin-calendar-trigger-label">De</span>
                                        <span class="admin-calendar-trigger-date" data-calendar-display>Selecione</span>
                                        <span class="admin-calendar-trigger-icon" aria-hidden="true">
                                            <svg viewBox="0 0 20 20">
                                                <path d="M6 2a1 1 0 0 1 1 1v1h6V3a1 1 0 1 1 2 0v1h1.5A2.5 2.5 0 0 1 19 6.5v9A2.5 2.5 0 0 1 16.5 18h-13A2.5 2.5 0 0 1 1 15.5v-9A2.5 2.5 0 0 1 3.5 4H5V3a1 1 0 0 1 1-1Zm-2.5 6v7.5c0 .55.45 1 1 1h13a1 1 0 0 0 1-1V8h-15Z"></path>
                                            </svg>
                                        </span>
                                    </button>
                                    <div class="admin-calendar-popover" data-calendar-popover>
                                        <div class="admin-calendar__card">
                                            <div class="admin-calendar__header">
                                                <button type="button" class="admin-calendar__nav" data-calendar-prev aria-label="Mes anterior">
                                                    <svg viewBox="0 0 20 20" aria-hidden="true">
                                                        <path d="M12.8 4.7a1 1 0 0 1 0 1.4L9 9.9l3.8 3.8a1 1 0 0 1-1.4 1.4L6.9 10.6a1 1 0 0 1 0-1.4l4.5-4.5a1 1 0 0 1 1.4 0z"></path>
                                                    </svg>
                                                </button>
                                                <div class="admin-calendar__month" data-calendar-title></div>
                                                <button type="button" class="admin-calendar__nav" data-calendar-next aria-label="Mes seguinte">
                                                    <svg viewBox="0 0 20 20" aria-hidden="true">
                                                        <path d="M7.2 15.3a1 1 0 0 1 0-1.4L11 10.1 7.2 6.3A1 1 0 1 1 8.6 4.9l4.5 4.5a1 1 0 0 1 0 1.4l-4.5 4.5a1 1 0 0 1-1.4 0z"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="admin-calendar__weekdays" data-calendar-weekdays></div>
                                            <div class="admin-calendar__grid" data-calendar-grid></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="admin-calendar admin-calendar-control" data-calendar data-field="to" data-value="{{ $filterTo ?? '' }}">
                                    <input type="hidden" name="to" data-calendar-input value="{{ $filterTo ?? '' }}">
                                    <button type="button" class="admin-calendar-trigger" data-calendar-trigger aria-haspopup="dialog" aria-expanded="false">
                                        <span class="admin-calendar-trigger-label">Ate</span>
                                        <span class="admin-calendar-trigger-date" data-calendar-display>Selecione</span>
                                        <span class="admin-calendar-trigger-icon" aria-hidden="true">
                                            <svg viewBox="0 0 20 20">
                                                <path d="M6 2a1 1 0 0 1 1 1v1h6V3a1 1 0 1 1 2 0v1h1.5A2.5 2.5 0 0 1 19 6.5v9A2.5 2.5 0 0 1 16.5 18h-13A2.5 2.5 0 0 1 1 15.5v-9A2.5 2.5 0 0 1 3.5 4H5V3a1 1 0 0 1 1-1Zm-2.5 6v7.5c0 .55.45 1 1 1h13a1 1 0 0 0 1-1V8h-15Z"></path>
                                            </svg>
                                        </span>
                                    </button>
                                    <div class="admin-calendar-popover" data-calendar-popover>
                                        <div class="admin-calendar__card">
                                            <div class="admin-calendar__header">
                                                <button type="button" class="admin-calendar__nav" data-calendar-prev aria-label="Mes anterior">
                                                    <svg viewBox="0 0 20 20" aria-hidden="true">
                                                        <path d="M12.8 4.7a1 1 0 0 1 0 1.4L9 9.9l3.8 3.8a1 1 0 0 1-1.4 1.4L6.9 10.6a1 1 0 0 1 0-1.4l4.5-4.5a1 1 0 0 1 1.4 0z"></path>
                                                    </svg>
                                                </button>
                                                <div class="admin-calendar__month" data-calendar-title></div>
                                                <button type="button" class="admin-calendar__nav" data-calendar-next aria-label="Mes seguinte">
                                                    <svg viewBox="0 0 20 20" aria-hidden="true">
                                                        <path d="M7.2 15.3a1 1 0 0 1 0-1.4L11 10.1 7.2 6.3A1 1 0 1 1 8.6 4.9l4.5 4.5a1 1 0 0 1 0 1.4l-4.5 4.5a1 1 0 0 1-1.4 0z"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="admin-calendar__weekdays" data-calendar-weekdays></div>
                                            <div class="admin-calendar__grid" data-calendar-grid></div>
                                        </div>
                                    </div>
                                </div>
                                @if ($isAdmin)
                                    <div class="admin-select" x-data="{ open: false, value: '{{ $filterSector ?? 'all' }}', label: '{{ $filterSectorLabel ?? 'Todos' }}', sub: '{{ $filterSectorSub ?? 'Geral' }}' }" @keydown.escape.window="open = false">
                                        <input type="hidden" name="sector" :value="value">
                                        <button type="button"
                                            class="admin-select-button"
                                            @click="open = !open"
                                            :aria-expanded="open.toString()"
                                            aria-haspopup="listbox">
                                            <span class="admin-select-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24">
                                                    <path d="M12 2.5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 12 2.5Zm6.9 8.5h-3.18a14.3 14.3 0 0 0-1.5-5.1 7.52 7.52 0 0 1 4.68 5.1Zm-6.9-6.1a12.3 12.3 0 0 1 1.9 6.1H10.1A12.3 12.3 0 0 1 12 4.9ZM5.1 11a7.52 7.52 0 0 1 4.68-5.1A14.3 14.3 0 0 0 8.28 11ZM4.5 13.5h3.18a14.3 14.3 0 0 0 1.5 5.1 7.52 7.52 0 0 1-4.68-5.1Zm6.4 0h3.82A12.3 12.3 0 0 1 12 19.1a12.3 12.3 0 0 1-1.9-5.6Zm5.22 0h3.18a7.52 7.52 0 0 1-4.68 5.1 14.3 14.3 0 0 0 1.5-5.1Z"></path>
                                                </svg>
                                            </span>
                                            <span class="admin-select-text">
                                                <span class="admin-select-label" x-text="label"></span>
                                                <span class="admin-select-sub" x-text="sub"></span>
                                            </span>
                                            <span class="admin-select-chevron" aria-hidden="true">
                                                <svg viewBox="0 0 20 20">
                                                    <path d="M5.4 7.6a1 1 0 0 1 1.4 0L10 10.8l3.2-3.2a1 1 0 1 1 1.4 1.4l-3.9 3.9a1 1 0 0 1-1.4 0L5.4 9a1 1 0 0 1 0-1.4z"></path>
                                                </svg>
                                            </span>
                                        </button>
                                        <div class="admin-select-menu" x-show="open" x-cloak @click.outside="open = false" role="listbox">
                                            <button type="button" class="admin-select-option" @click="value = 'all'; label = 'Todos'; sub = 'Geral'; open = false;">
                                                <span class="admin-select-option-text">
                                                    <span class="admin-select-option-title">Todos</span>
                                                    <span class="admin-select-option-sub">Geral</span>
                                                </span>
                                                <span class="admin-select-check" x-show="value === 'all'" x-cloak>
                                                    <svg viewBox="0 0 16 16">
                                                        <path d="M6.5 11.3 3.7 8.5a1 1 0 0 1 1.4-1.4l1.4 1.4 4-4a1 1 0 1 1 1.4 1.4l-4.7 4.7a1 1 0 0 1-1.4 0z"></path>
                                                    </svg>
                                                </span>
                                            </button>
                                            <button type="button" class="admin-select-option" @click="value = 'juridico'; label = 'Juridico'; sub = 'Legal'; open = false;">
                                                <span class="admin-select-option-text">
                                                    <span class="admin-select-option-title">Juridico</span>
                                                    <span class="admin-select-option-sub">Legal</span>
                                                </span>
                                                <span class="admin-select-check" x-show="value === 'juridico'" x-cloak>
                                                    <svg viewBox="0 0 16 16">
                                                        <path d="M6.5 11.3 3.7 8.5a1 1 0 0 1 1.4-1.4l1.4 1.4 4-4a1 1 0 1 1 1.4 1.4l-4.7 4.7a1 1 0 0 1-1.4 0z"></path>
                                                    </svg>
                                                </span>
                                            </button>
                                            <button type="button" class="admin-select-option" @click="value = 'mkt'; label = 'MKT'; sub = 'Marketing'; open = false;">
                                                <span class="admin-select-option-text">
                                                    <span class="admin-select-option-title">MKT</span>
                                                    <span class="admin-select-option-sub">Marketing</span>
                                                </span>
                                                <span class="admin-select-check" x-show="value === 'mkt'" x-cloak>
                                                    <svg viewBox="0 0 16 16">
                                                        <path d="M6.5 11.3 3.7 8.5a1 1 0 0 1 1.4-1.4l1.4 1.4 4-4a1 1 0 1 1 1.4 1.4l-4.7 4.7a1 1 0 0 1-1.4 0z"></path>
                                                    </svg>
                                                </span>
                                            </button>
                                            <button type="button" class="admin-select-option" @click="value = 'rh'; label = 'RH'; sub = 'Recursos Humanos'; open = false;">
                                                <span class="admin-select-option-text">
                                                    <span class="admin-select-option-title">RH</span>
                                                    <span class="admin-select-option-sub">Recursos Humanos</span>
                                                </span>
                                                <span class="admin-select-check" x-show="value === 'rh'" x-cloak>
                                                    <svg viewBox="0 0 16 16">
                                                        <path d="M6.5 11.3 3.7 8.5a1 1 0 0 1 1.4-1.4l1.4 1.4 4-4a1 1 0 1 1 1.4 1.4l-4.7 4.7a1 1 0 0 1-1.4 0z"></path>
                                                    </svg>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                                <button type="submit" class="admin-filter-button">Aplicar</button>
                            </form>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-[1.4fr_0.6fr]">
                            <div class="admin-hero space-y-4">
                                <span class="admin-pill">{{ $summaryTitle ?? 'Resumo' }}</span>
                                <div>
                                    <h1 class="admin-title">{{ $heroTitle ?? 'Dashboard' }}</h1>
                                    <p class="admin-sub">
                                        {{ $heroSub ?? '' }}
                                    </p>
                                </div>
                                <div>
                                    <div class="admin-segments">
                                        @foreach (($sectorCounts ?? []) as $sector)
                                            <span class="admin-segment" style="--segment: {{ $sector['share'] }}; --segment-color: {{ $sector['color'] }}"></span>
                                        @endforeach
                                    </div>
                                    <div class="admin-legend mt-3">
                                        @foreach (($sectorCounts ?? []) as $sector)
                                            <span class="admin-legend-item">
                                                <span class="admin-legend-dot" style="--legend-color: {{ $sector['color'] }}"></span>
                                                <span>{{ $sector['label'] }}</span>
                                                <span class="admin-legend-value">{{ $sector['count'] }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <a href="{{ route('calls.index') }}" class="admin-cta btn-accent">
                                        Ver chamados
                                    </a>
                                    <span class="admin-note-pill">Periodo: {{ $periodLabel ?? '--' }}</span>
                                    <span class="admin-note-pill">Tempo medio de aceite: {{ $stats['avg_accept'] ?? '0m' }}</span>
                                </div>
                            </div>

                            <div class="admin-kpi-stack grid gap-3">
                                <div class="admin-kpi">
                                    <div class="admin-kpi__value">{{ $stats['total'] ?? 0 }}</div>
                                    <div class="admin-kpi__label">Demandas no periodo</div>
                                    <div class="admin-kpi__meta">{{ $stats['growth'] ?? '0%' }} no periodo anterior</div>
                                </div>
                                <div class="admin-kpi">
                                    <div class="admin-kpi__value">{{ $stats['pending'] ?? 0 }}</div>
                                    <div class="admin-kpi__label">Aguardando aceite</div>
                                    <div class="admin-kpi__meta">{{ $stats['accept_rate'] ?? '0%' }} aceitas</div>
                                </div>
                                <div class="admin-kpi">
                                    <div class="admin-kpi__value">{{ $stats['rejected'] ?? 0 }}</div>
                                    <div class="admin-kpi__label">Recusadas</div>
                                    <div class="admin-kpi__meta">{{ $stats['reject_rate'] ?? '0%' }} de recusas</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-6 lg:grid-cols-3">
                            <div class="admin-panel">
                                <div class="admin-panel__title">{{ $sectorPanelTitle ?? 'Demandas' }}</div>
                                <div class="admin-bar-list">
                                    @foreach (($sectorCounts ?? []) as $sector)
                                        <div class="admin-bar-row">
                                            <div class="admin-bar-label">
                                                <span class="admin-bar-dot" style="--dot-color: {{ $sector['color'] }}"></span>
                                                <span>{{ $sector['label'] }}</span>
                                            </div>
                                            <div class="admin-bar-track">
                                                <span class="admin-bar-fill" style="--bar-color: {{ $sector['color'] }}; --bar-value: {{ $sector['share'] }}"></span>
                                            </div>
                                            <div class="admin-bar-value">{{ $sector['count'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="admin-panel admin-panel--center">
                                <div class="admin-panel__title">Tempo medio para aceitar</div>
                                <div class="admin-ring" style="--ring-value: {{ $ringValue ?? 0 }}; --ring-color: {{ $ringColor ?? '#63BE15' }};">
                                    <div class="admin-ring__inner">
                                        <div class="admin-ring__value">{{ $stats['avg_accept'] ?? '0m' }}</div>
                                        <div class="admin-ring__label">{{ $ringLabel ?? 'media' }}</div>
                                    </div>
                                </div>
                                <div class="admin-panel__hint">Meta: manter abaixo de 3h</div>
                            </div>

                            <div class="admin-panel">
                                <div class="admin-panel__title">Recusas recentes</div>
                                <div class="admin-list">
                                    @if (empty($recentRejections))
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            Nenhuma recusa no periodo.
                                        </div>
                                    @else
                                        @foreach ($recentRejections as $item)
                                            <div class="admin-list-item">
                                                <div>
                                                    <div class="admin-list-title">{{ $item['title'] }}</div>
                                                    <div class="admin-list-sub">{{ $item['time'] }}</div>
                                                </div>
                                                <span class="admin-list-pill">Recusado</span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="admin-panel admin-panel--wide">
                                <div class="admin-panel__title">{{ $summaryFooterTitle ?? 'Resumo' }}</div>
                                <div class="admin-mini-grid">
                                    @foreach (($summaryCards ?? []) as $item)
                                        <div class="admin-mini-card">
                                            <div class="admin-mini-value">{{ $item['value'] }}</div>
                                            <div class="admin-mini-label">{{ $item['label'] }}</div>
                                            <div class="admin-mini-trend">{{ $item['trend'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="admin-panel">
                                <div class="admin-panel__title">Relatorios</div>
                                <div class="admin-report-grid">
                                    @foreach (($reportSummary ?? []) as $item)
                                        <div class="admin-report-item">
                                            <div class="admin-report-value">{{ $item['value'] }}</div>
                                            <div class="admin-report-label">{{ $item['label'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-4">
                                    <a href="{{ route('reports.export', request()->query()) }}" class="admin-report-button">
                                        Exportar CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100 space-y-3">
                        <div class="text-lg font-semibold">Dashboard</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Os chamados agora ficam no menu do icone da Amelia.
                        </div>
                        <div>
                            <a href="{{ route('calls.index') }}"
                                class="rounded-md btn-accent px-4 py-2 text-sm inline-flex items-center">
                                Ver chamados
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        (() => {
            const calendars = document.querySelectorAll('[data-calendar]');
            if (!calendars.length) {
                return;
            }

            const monthNames = [
                'Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho',
                'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
            ];
            const weekdayLabels = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];

            const parseDate = (value) => {
                if (!value) {
                    return null;
                }
                const parts = value.split('-').map((item) => parseInt(item, 10));
                if (parts.length !== 3 || parts.some((item) => Number.isNaN(item))) {
                    return null;
                }
                return new Date(parts[0], parts[1] - 1, parts[2]);
            };

            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            const isSameDay = (a, b) => {
                if (!a || !b) {
                    return false;
                }
                return a.getFullYear() === b.getFullYear()
                    && a.getMonth() === b.getMonth()
                    && a.getDate() === b.getDate();
            };

            calendars.forEach((calendar) => {
                const input = calendar.querySelector('[data-calendar-input]');
                const display = calendar.querySelector('[data-calendar-display]');
                const trigger = calendar.querySelector('[data-calendar-trigger]');
                const popover = calendar.querySelector('[data-calendar-popover]');
                if (!input || !display || !trigger || !popover) {
                    return;
                }

                const initialValue = calendar.dataset.value || input.value;
                let selected = parseDate(initialValue);
                let viewDate = selected ? new Date(selected.getFullYear(), selected.getMonth(), 1) : new Date();
                viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);

                const titleEl = calendar.querySelector('[data-calendar-title]');
                const gridEl = calendar.querySelector('[data-calendar-grid]');
                const weekdaysEl = calendar.querySelector('[data-calendar-weekdays]');
                const prevBtn = calendar.querySelector('[data-calendar-prev]');
                const nextBtn = calendar.querySelector('[data-calendar-next]');

                const renderWeekdays = () => {
                    if (!weekdaysEl || weekdaysEl.dataset.ready) {
                        return;
                    }
                    weekdayLabels.forEach((label) => {
                        const day = document.createElement('div');
                        day.textContent = label;
                        day.className = 'admin-calendar__weekday';
                        weekdaysEl.appendChild(day);
                    });
                    weekdaysEl.dataset.ready = 'true';
                };

                const renderDisplay = () => {
                    display.textContent = selected ? selected.toLocaleDateString('pt-BR') : 'Selecione';
                };

                const render = () => {
                    if (!gridEl) {
                        return;
                    }
                    const year = viewDate.getFullYear();
                    const month = viewDate.getMonth();
                    if (titleEl) {
                        titleEl.textContent = `${monthNames[month]} ${year}`;
                    }

                    gridEl.innerHTML = '';
                    const firstDay = new Date(year, month, 1).getDay();
                    const daysInMonth = new Date(year, month + 1, 0).getDate();
                    const prevMonthDays = new Date(year, month, 0).getDate();
                    const today = new Date();
                    const totalCells = 42;

                    for (let i = 0; i < totalCells; i += 1) {
                        let dayNumber;
                        let offset = 0;
                        if (i < firstDay) {
                            dayNumber = prevMonthDays - firstDay + i + 1;
                            offset = -1;
                        } else if (i >= firstDay + daysInMonth) {
                            dayNumber = i - firstDay - daysInMonth + 1;
                            offset = 1;
                        } else {
                            dayNumber = i - firstDay + 1;
                        }

                        const cellDate = new Date(year, month + offset, dayNumber);
                        const isCurrentMonth = offset === 0;
                        const dayButton = document.createElement('button');
                        dayButton.type = 'button';
                        dayButton.textContent = String(dayNumber);
                        dayButton.className = 'admin-calendar__day';

                        if (!isCurrentMonth) {
                            dayButton.classList.add('is-muted');
                        }
                        if (isSameDay(cellDate, today)) {
                            dayButton.classList.add('is-today');
                        }
                        if (selected && isSameDay(cellDate, selected)) {
                            dayButton.classList.add('is-selected');
                        }

                        dayButton.addEventListener('click', () => {
                            selected = cellDate;
                            input.value = formatDate(cellDate);
                            renderDisplay();
                            if (!isCurrentMonth) {
                                viewDate = new Date(cellDate.getFullYear(), cellDate.getMonth(), 1);
                            }
                            render();
                            calendar.classList.remove('is-open');
                            trigger.setAttribute('aria-expanded', 'false');
                        });

                        gridEl.appendChild(dayButton);
                    }
                };

                renderWeekdays();
                render();
                renderDisplay();

                if (prevBtn) {
                    prevBtn.addEventListener('click', () => {
                        viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
                        render();
                    });
                }
                if (nextBtn) {
                    nextBtn.addEventListener('click', () => {
                        viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
                        render();
                    });
                }

                trigger.addEventListener('click', () => {
                    calendars.forEach((item) => {
                        if (item !== calendar) {
                            item.classList.remove('is-open');
                            const otherTrigger = item.querySelector('[data-calendar-trigger]');
                            if (otherTrigger) {
                                otherTrigger.setAttribute('aria-expanded', 'false');
                            }
                        }
                    });
                    const isOpen = calendar.classList.toggle('is-open');
                    trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
            });

            document.addEventListener('click', (event) => {
                calendars.forEach((calendar) => {
                    if (!calendar.contains(event.target)) {
                        calendar.classList.remove('is-open');
                        const trigger = calendar.querySelector('[data-calendar-trigger]');
                        if (trigger) {
                            trigger.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            });
        })();
    </script>
</x-app-layout>
