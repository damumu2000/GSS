        #attachment-library-modal .site-select { position: relative; }
        #attachment-library-modal .site-select-native { position: absolute; inset: 0; opacity: 0; pointer-events: none; }
        #attachment-library-modal .site-select-trigger {
            appearance: none;
            -webkit-appearance: none;
            width: 100%;
            min-height: 42px;
            padding: 0 40px 0 14px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            color: #374151;
            font: inherit;
            font-size: 14px;
            font-weight: 500;
            line-height: 42px;
            text-align: left;
            cursor: pointer;
            position: relative;
            box-shadow: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }
        #attachment-library-modal .site-select-trigger:hover { border-color: #d1d5db; background: #fcfcfd; }
        #attachment-library-modal .site-select-trigger::after {
            content: '';
            position: absolute;
            right: 14px;
            top: 50%;
            width: 12px;
            height: 12px;
            transform: translateY(-50%);
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12' fill='none'%3E%3Cpath d='M3 4.5L6 7.5L9 4.5' stroke='%2398A2B3' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center/12px 12px no-repeat;
            transition: transform 0.18s ease;
        }
        #attachment-library-modal .site-select.is-open .site-select-trigger::after { transform: translateY(-50%) rotate(180deg); }
        #attachment-library-modal .site-select.is-open .site-select-trigger,
        #attachment-library-modal .site-select-trigger:focus-visible {
            outline: none;
            border-color: rgba(0, 71, 171, 0.26);
            box-shadow: 0 0 0 3px rgba(0, 71, 171, 0.10);
        }
        #attachment-library-modal .site-select-panel {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            padding: 8px;
            border: 1px solid #eef2f7;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.10);
            opacity: 0;
            pointer-events: none;
            transform: translateY(-4px);
            transition: opacity 0.18s ease, transform 0.18s ease;
            z-index: 120;
        }
        #attachment-library-modal .site-select.is-open .site-select-panel {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }
        #attachment-library-modal .site-select-option {
            appearance: none;
            -webkit-appearance: none;
            position: relative;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-height: 42px;
            padding: 9px 12px;
            margin: 0;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: #334155;
            font: inherit;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.5;
            text-align: left;
            cursor: pointer;
            box-shadow: none;
            transition: background 0.16s ease, color 0.16s ease;
        }
        #attachment-library-modal .site-select-option:hover,
        #attachment-library-modal .site-select-option.is-active { background: #f8fbff; color: #0f172a; }
        #attachment-library-modal .site-select-check {
            width: 14px;
            height: 14px;
            stroke: #4b5563;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            opacity: 0;
            flex-shrink: 0;
        }
        #attachment-library-modal .site-select-option.is-active .site-select-check { opacity: 1; }
        .attachment-library-modal[hidden] { display: none; }
        .attachment-library-modal { position: fixed; inset: 0; z-index: 2400; }
        .attachment-library-backdrop { position: absolute; inset: 0; background: rgba(17, 31, 27, 0.56); backdrop-filter: blur(3px); }
        .attachment-library-panel { position: relative; width: min(980px, calc(100% - 32px)); max-height: calc(100vh - 56px); margin: 28px auto; padding: 24px; border-radius: 28px; background: #fff; border: 1px solid #d7e3dc; box-shadow: 0 24px 60px rgba(17, 31, 27, 0.24); overflow: auto; }
        .attachment-library-header { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 18px; }
        .attachment-library-header h3 { margin: 0 0 6px; font-size: 24px; }
        .attachment-library-rule-text {
            display: block;
            font-size: 12px;
            line-height: 1.65;
            color: #98a2b3;
        }
        .attachment-library-rule-copy {
            display: inline;
        }
        .attachment-library-feature-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: inherit;
            font-size: inherit;
            font-weight: inherit;
            line-height: inherit;
            vertical-align: baseline;
        }
        .attachment-library-feature-badge::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #22c55e;
            flex-shrink: 0;
        }
        .attachment-library-toolbar { display: grid; grid-template-columns: minmax(0, 1fr) 160px 180px 190px; gap: 14px; margin-bottom: 18px; }
        .attachment-library-upload { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
        .attachment-library-contextbar { margin-bottom: 18px; }
        .attachment-library-bulkbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .attachment-library-singlebar {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            min-height: 24px;
        }
        .attachment-library-singlebar .muted {
            font-size: 13px;
            line-height: 1.6;
        }
        .image-insert-panel { padding: 18px; margin-bottom: 18px; border-radius: 18px; background: #f3f8f5; border: 1px solid #d7e3dc; }
        .image-insert-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
        .image-insert-panel .action-row { margin-top: 14px; }
        .attachment-library-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .attachment-library-pagination { display: flex; justify-content: flex-end; margin-top: 18px; }
        .attachment-library-pagination nav { width: 100%; display: flex; justify-content: flex-end; }
        .attachment-library-pagination .pagination-shell { display: inline-flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
        .attachment-library-pagination .pagination-pages { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .attachment-library-pagination .pagination-button,
        .attachment-library-pagination .pagination-page,
        .attachment-library-pagination .pagination-ellipsis { min-width: 42px; height: 42px; border-radius: 14px; border: 1px solid #e5e7eb; background: #ffffff; color: #6b7280; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; cursor: pointer; transition: all 0.2s ease; }
        .attachment-library-pagination .pagination-page { min-width: 42px; padding: 0 12px; }
        .attachment-library-pagination .pagination-button { min-width: auto; padding: 0 14px; gap: 6px; }
        .attachment-library-pagination .pagination-button:hover,
        .attachment-library-pagination .pagination-page:hover { border-color: #d1d5db; background: #f8fafc; color: #374151; }
        .attachment-library-pagination .pagination-page.is-active,
        .attachment-library-pagination .pagination-page.is-active:visited { background: #374151; border-color: #374151; color: #ffffff; box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12); }
        .attachment-library-pagination .pagination-button.is-disabled,
        .attachment-library-pagination .pagination-page.is-disabled,
        .attachment-library-pagination .pagination-ellipsis { color: #c4c7cf; border-color: #eceef2; background: #ffffff; pointer-events: none; cursor: default; }
        .attachment-library-pagination .pagination-icon { width: 14px; height: 14px; stroke: currentColor; stroke-width: 1.8; fill: none; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
        .attachment-library-card { position: relative; padding: 16px; border-radius: 20px; border: 1px solid #d7e3dc; background: #f7fbf8; display: grid; gap: 12px; }
        .attachment-library-card.is-used { background: #f8fbff; border-color: #dbe7f5; }
        .attachment-library-card.selected { border-color: #206a5d; box-shadow: 0 0 0 2px rgba(32, 106, 93, 0.12); }
        .attachment-library-select { position: absolute; top: 12px; right: 12px; }
        .attachment-library-select input { width: 22px; height: 22px; margin: 0; border-radius: 7px; accent-color: var(--primary); cursor: pointer; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08); }
        .attachment-library-panel.is-single-select .attachment-library-select { display: none; }
        .attachment-library-preview { display: flex; align-items: center; justify-content: center; min-height: 140px; border-radius: 16px; background: #eef6f2; overflow: hidden; color: #60756d; font-weight: 600; }
        .attachment-library-card.is-used .attachment-library-preview { background: linear-gradient(135deg, #eff5ff 0%, #e8f0ff 100%); }
        .attachment-library-preview img { width: 100%; height: 140px; object-fit: cover; }
        .attachment-library-meta { display: grid; gap: 6px; }
        .attachment-library-name { font-size: 15px; line-height: 1.6; font-weight: 500; word-break: break-all; }
        .attachment-library-ext { display: flex; align-items: center; justify-content: space-between; gap: 10px; color: #60756d; font-size: 13px; }
        .attachment-library-ext > span:first-child { min-width: 0; }
        .attachment-library-dimension { color: #94a3b8; font-size: 11px; line-height: 1.4; font-weight: 500; }
        .attachment-library-submeta { color: #98a2b3; font-size: 12px; line-height: 1.6; }
        .attachment-library-usage { display: flex; align-items: center; gap: 6px; color: #206a5d; font-size: 12px; font-weight: 600; }
        .attachment-library-usage > .attachment-library-submeta { margin-left: auto; text-align: right; }
        .attachment-library-usage-link { display: inline-flex; align-items: center; gap: 6px; border: 0; background: transparent; color: #4b5563; font-size: 12px; font-weight: 700; line-height: 1; cursor: pointer; padding: 0; }
        .attachment-library-usage-link:hover { color: #111827; }
        .attachment-library-usage-link svg { width: 13px; height: 13px; stroke: currentColor; stroke-width: 1.9; fill: none; }
        .attachment-library-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .attachment-library-actions .button,
        .attachment-library-actions .button.secondary { min-height: 34px; padding: 0 12px; font-size: 13px; border-radius: 10px; }
        .attachment-library-actions .attachment-library-replace { margin-left: auto; }
        .attachment-library-actions .attachment-library-delete { margin-left: auto; }
        .attachment-library-actions .danger-lite { color: #6b7280; border-color: #e5e7eb; background: #fff; }
        .attachment-library-used-note { display: inline-flex; align-items: center; gap: 6px; color: #64748b; font-size: 13px; line-height: 1; font-weight: 700; padding: 0 2px; flex-shrink: 0; white-space: nowrap; }
        .attachment-library-used-note::before { content: ''; width: 8px; height: 8px; border-radius: 999px; background: #52c41a; box-shadow: 0 0 0 0 rgba(82, 196, 26, 0.36); animation: attachment-library-used-pulse 1.9s ease-out infinite; }
        .attachment-library-empty { padding: 28px; border-radius: 20px; background: #f7fbf8; color: #60756d; text-align: center; }
        .attachment-usage-modal[hidden] { display: none; }
        .attachment-usage-modal { position: fixed; inset: 0; z-index: 2450; }
        .attachment-usage-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.48); backdrop-filter: blur(8px); }
        .attachment-usage-panel { position: relative; width: min(860px, calc(100vw - 40px)); max-height: calc(100vh - 56px); margin: 28px auto; padding: 28px; border-radius: 28px; background: #fff; border: 1px solid #eef1f5; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16); overflow: auto; }
        .attachment-usage-header { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; }
        .attachment-usage-title { margin: 0; color: #1f2937; font-size: 24px; line-height: 1.35; font-weight: 700; }
        .attachment-usage-desc { margin-top: 8px; color: #8b94a7; font-size: 14px; line-height: 1.7; word-break: break-all; }
        .attachment-usage-close { width: 40px; height: 40px; border-radius: 14px; border: 1px solid #e7ebf1; background: #fff; color: #667085; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
        .attachment-usage-close:hover { background: #f8fafc; color: #344054; }
        .attachment-usage-close svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 1.9; fill: none; }
        .attachment-usage-list { display: grid; gap: 14px; margin-top: 22px; }
        .attachment-usage-item { padding: 18px 18px 16px; border-radius: 20px; border: 1px solid #eef1f5; background: linear-gradient(180deg, #ffffff 0%, #fafcff 100%); }
        .attachment-usage-item-header { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
        .attachment-usage-item-title { margin: 0; color: #1f2937; font-size: 16px; line-height: 1.6; font-weight: 700; }
        .attachment-usage-item-updated { color: #98a2b3; font-size: 12px; line-height: 1.5; white-space: nowrap; }
        .attachment-usage-badges { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 10px; }
        .attachment-usage-badge { display: inline-flex; align-items: center; justify-content: center; min-height: 28px; padding: 0 12px; border-radius: 999px; background: #f5f7fb; color: #4b5563; font-size: 12px; font-weight: 700; }
        .attachment-usage-badge.is-position { background: rgba(0, 71, 171, 0.08); color: var(--primary); }
        .attachment-usage-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 14px; }
        .attachment-usage-empty { margin-top: 22px; padding: 36px 20px; text-align: center; color: #8b94a7; border-radius: 20px; border: 1px dashed #d8e1eb; background: #fbfdff; }
        .attachment-usage-loading { margin-top: 22px; color: #667085; font-size: 14px; line-height: 1.8; }
        @keyframes attachment-library-used-pulse {
            0% { box-shadow: 0 0 0 0 rgba(82, 196, 26, 0.36); }
            70% { box-shadow: 0 0 0 6px rgba(82, 196, 26, 0); }
            100% { box-shadow: 0 0 0 0 rgba(82, 196, 26, 0); }
        }
        @media (max-width: 920px) {
            .attachment-library-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 680px) {
            .attachment-library-panel { margin: 16px auto; padding: 18px; width: calc(100% - 20px); }
            .attachment-library-toolbar { grid-template-columns: 1fr; }
            .attachment-library-grid { grid-template-columns: 1fr; }
            .attachment-library-pagination,
            .attachment-library-pagination nav,
            .attachment-library-pagination .pagination-shell { justify-content: center; }
        }
