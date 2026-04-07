<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        align-items: flex-start;
        padding: 24px 32px;
        margin: -28px -28px 24px;
        background: #ffffff;
        border-bottom: 1px solid #f0f0f0;
    }

    .page-header-title {
        margin: 0;
        color: #262626;
        font-size: 20px;
        line-height: 1.4;
        font-weight: 700;
    }

    .page-header-desc {
        margin-top: 8px;
        color: #8c8c8c;
        font-size: 14px;
        line-height: 1.7;
    }

    .platform-user-shell {
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        overflow: visible;
    }

    .platform-user-body {
        padding: 24px 28px 28px;
    }

    .platform-user-layout-grid {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
        gap: 24px;
        align-items: start;
    }

    .platform-user-column {
        display: grid;
        gap: 24px;
        min-width: 0;
    }

    .platform-user-module {
        background: #ffffff;
        border: 1px solid #f3f4f6;
        border-radius: 14px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
        overflow: hidden;
    }

    .platform-user-module-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 18px 20px;
        border-bottom: 1px solid #f5f5f5;
        background: #ffffff;
    }

    .platform-user-module-accent {
        width: 6px;
        height: 30px;
        border-radius: 999px;
        background: var(--primary);
        flex-shrink: 0;
        opacity: 0.9;
    }

    .platform-user-module-title {
        color: #262626;
        font-size: 16px;
        line-height: 1.5;
        font-weight: 700;
    }

    .platform-user-module-body {
        padding: 20px;
        display: grid;
        gap: 18px;
    }

    .platform-user-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .field-group {
        display: grid;
        gap: 8px;
    }

    .field-label {
        color: #8c8c8c;
        font-size: 13px;
        font-weight: 500;
        line-height: 1.5;
    }

    .field-note {
        color: #8c8c8c;
        font-size: 12px;
        line-height: 1.7;
    }

    .platform-user-role-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .platform-user-role-chip {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 999px;
        background: #fafafa;
        color: #595959;
        box-shadow: inset 0 0 0 1px #eceff3;
        cursor: pointer;
        transition: background 0.18s ease, box-shadow 0.18s ease, color 0.18s ease, transform 0.18s ease;
    }

    .platform-user-role-chip:hover {
        background: var(--primary-bg);
        box-shadow: inset 0 0 0 1px var(--primary-border-soft);
        color: var(--primary);
        transform: translateY(-1px);
    }

    .platform-user-role-chip.is-disabled {
        cursor: not-allowed;
        opacity: 0.72;
        transform: none;
    }

    .platform-user-role-chip.is-disabled:hover {
        background: #fafafa;
        box-shadow: inset 0 0 0 1px #eceff3;
        color: #595959;
        transform: none;
    }

    .platform-user-role-chip.is-error {
        background: rgba(254, 242, 242, 0.9);
        box-shadow: inset 0 0 0 1px rgba(239, 68, 68, 0.34), 0 0 0 3px rgba(239, 68, 68, 0.08);
        color: #b42318;
    }

    .platform-user-role-chip input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .platform-user-role-chip input:disabled {
        cursor: not-allowed;
    }

    .platform-user-role-dot {
        width: 16px;
        height: 16px;
        border-radius: 999px;
        background: #ffffff;
        box-shadow: inset 0 0 0 1px #d9d9d9;
        position: relative;
        flex-shrink: 0;
    }

    .platform-user-role-dot::after {
        content: "";
        position: absolute;
        inset: 4px;
        border-radius: 999px;
        background: #ffffff;
        opacity: 0;
    }

    .platform-user-role-name {
        font-size: 13px;
        line-height: 1.4;
        font-weight: 600;
    }

    .platform-user-role-chip:has(input:checked) {
        background: var(--tag-bg);
        box-shadow: inset 0 0 0 1px var(--primary-border-soft);
        color: var(--tag-text);
    }

    .platform-user-role-chip input:checked + .platform-user-role-dot {
        background: var(--primary);
        box-shadow: inset 0 0 0 1px var(--primary);
    }

    .platform-user-role-chip input:checked + .platform-user-role-dot::after {
        opacity: 1;
    }

    .platform-user-status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        width: 100%;
        padding: 0 14px;
        border-radius: 999px;
        background: var(--tag-bg);
        color: var(--tag-text);
        font-size: 14px;
        line-height: 1.2;
        font-weight: 700;
    }

    .platform-user-status-divider {
        border-top: 1px dashed #f0f0f0;
    }

    .platform-user-status-meta {
        display: grid;
        gap: 12px;
    }

    .platform-user-status-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .platform-user-status-label {
        color: #8c8c8c;
        font-size: 13px;
        line-height: 1.5;
    }

    .platform-user-status-value {
        color: #262626;
        font-size: 14px;
        line-height: 1.5;
        font-weight: 700;
        text-align: right;
    }

    .platform-user-summary-card {
        display: grid;
        gap: 14px;
    }

    .platform-user-summary-list {
        display: grid;
        gap: 10px;
    }

    .platform-user-summary-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 10px;
        background: #fafafa;
    }

    .platform-user-summary-key {
        color: #8c8c8c;
        font-size: 12px;
        line-height: 1.5;
    }

    .platform-user-summary-value {
        color: #262626;
        font-size: 13px;
        line-height: 1.5;
        font-weight: 600;
        text-align: right;
    }

    .platform-user-footer-note {
        color: #8c8c8c;
        font-size: 12px;
        line-height: 1.8;
    }

    @media (max-width: 960px) {
        .platform-user-layout-grid,
        .platform-user-form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .page-header {
            margin: -24px -18px 20px;
            padding: 18px;
            flex-direction: column;
            align-items: flex-start;
        }

        .platform-user-body {
            padding: 18px;
        }
    }
</style>
