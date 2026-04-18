@php
    $embeddedModuleUi = (bool) ($embeddedModuleUi ?? false);
    $isModuleCreateSubmit = (string) old('_module_action') === 'create';
    $moduleSubmitError = $errors->first('module_id') ?: ($errors->first('is_trial') ?: $errors->first('is_paused'));
    $moduleCreateModalOpen = $isModuleCreateSubmit && $moduleSubmitError !== null;
@endphp

@if ($errors->has('module'))
    <section class="theme-callout is-error">
        <div class="theme-callout-title">模块操作未完成</div>
        <div class="theme-callout-text">{{ $errors->first('module') }}</div>
    </section>
@endif

<section class="site-modules-panel">
    <div class="site-modules-panel-head">
        <div>
            <h3 class="site-modules-panel-title">模块列表</h3>
            <div class="site-modules-panel-desc">可管理试用与停用状态，移除时会同步删除该模块在当前站点的数据。</div>
        </div>
        <button class="button" type="button" data-open-module-create-modal>添加模块</button>
    </div>

    @if ($boundModules->isEmpty())
        <div class="site-modules-empty">当前站点尚未绑定模块，请先添加模块。</div>
    @else
        <div class="site-modules-list is-redesigned">
            @foreach ($boundModules as $module)
                <article class="site-module-row is-redesigned">
                    <div class="site-module-main">
                        <h4>{{ $module->name }}</h4>
                        <div class="site-module-meta">
                            <span class="site-module-code">{{ $module->code }}</span>
                            <div class="site-module-state">
                                @if ((int) $module->is_trial === 1)
                                    <span class="site-module-badge is-trial">试用中</span>
                                @endif
                                @if ((int) $module->is_paused === 1)
                                    <span class="site-module-badge is-paused">已停用</span>
                                @endif
                                @if ((int) $module->is_trial !== 1 && (int) $module->is_paused !== 1)
                                    <span class="site-module-badge is-normal">正常运行</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="site-module-actions">
                        <form method="POST" action="{{ route('admin.platform.sites.modules.update', ['site' => $site->id, 'module' => $module->id]) }}" class="site-module-form is-redesigned">
                            @csrf
                            @if ($embeddedModuleUi)
                                <input type="hidden" name="_module_ui" value="embedded">
                            @endif
                            <label class="site-modules-flag">
                                <input type="checkbox" name="is_trial" value="1" @checked((int) $module->is_trial === 1)>
                                <span>试用</span>
                            </label>
                            <label class="site-modules-flag">
                                <input type="checkbox" name="is_paused" value="1" @checked((int) $module->is_paused === 1)>
                                <span>停用</span>
                            </label>
                            <button class="button" type="submit">保存</button>
                        </form>
                        <form method="POST" action="{{ route('admin.platform.sites.modules.remove', ['site' => $site->id, 'module' => $module->id]) }}" class="site-module-remove-form" data-remove-site-module-form data-module-name="{{ $module->name }}">
                            @csrf
                            @if ($embeddedModuleUi)
                                <input type="hidden" name="_module_ui" value="embedded">
                            @endif
                            <button class="button danger" type="submit">移除</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

<section class="site-module-create-modal @if ($moduleCreateModalOpen) is-open @endif" data-site-module-create-modal @if (! $moduleCreateModalOpen) hidden @endif>
    <div class="site-module-create-modal-backdrop" data-close-module-create-modal></div>
    <div class="site-module-create-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="site-module-create-modal-title">
        <div class="site-module-create-modal-head">
            <div class="site-module-create-modal-title" id="site-module-create-modal-title">添加模块</div>
            <button class="site-module-create-modal-close" type="button" data-close-module-create-modal aria-label="关闭添加模块弹窗">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12"/><path d="M18 6 6 18"/></svg>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.platform.sites.modules.add', $site->id) }}" class="site-module-create-modal-body">
            @csrf
            <input type="hidden" name="_module_action" value="create">
            @if ($embeddedModuleUi)
                <input type="hidden" name="_module_ui" value="embedded">
            @endif
            <label class="field-group">
                <span class="field-label">选择模块</span>
                <select class="field site-module-create-select @if ($errors->has('module_id')) is-error @endif" name="module_id" required @if ($errors->has('module_id')) aria-invalid="true" @endif>
                    <option value="">请选择模块</option>
                    @foreach ($availableModules as $module)
                        <option value="{{ (int) $module['id'] }}" @selected((string) old('module_id') === (string) $module['id'])>{{ $module['name'] }}（{{ $module['code'] }}）</option>
                    @endforeach
                </select>
            </label>
            @if (collect($availableModules)->isEmpty())
                <div class="site-modules-empty">当前没有可添加模块，请先确认平台模块已启用且未被绑定。</div>
            @endif
            @if ($moduleSubmitError)
                <div class="form-error">{{ $moduleSubmitError }}</div>
            @endif
            <div class="site-module-create-modal-actions">
                <button class="button secondary" type="button" data-close-module-create-modal>取消</button>
                <button class="button" type="submit">确认添加</button>
            </div>
        </form>
    </div>
</section>
