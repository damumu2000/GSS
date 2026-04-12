@extends('layouts.admin')

@section('title', '编辑工资批次 - 功能模块 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 工资信息 / 编辑工资批次')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/payroll-admin-batches-edit.css') }}">
@endpush

@section('content')
    <form method="POST" action="{{ route('admin.payroll.batches.update', $batch->id) }}" enctype="multipart/form-data" id="payroll-batch-edit-form">
        @csrf

        <div class="payroll-header">
            <div>
                <h1 class="payroll-header-title">编辑工资批次</h1>
                <div class="payroll-header-desc">重新上传工资表或绩效表后，系统会立即重新解析并覆盖当前月份对应结果。</div>
            </div>
            <div class="page-header-actions">
                <a class="button secondary" href="{{ route('admin.payroll.batches.index') }}">返回列表</a>
            </div>
        </div>

        @include('payroll::admin._nav')

        <div class="payroll-edit-shell">
            @if ($errors->any())
                <section class="payroll-edit-errors">
                    <h2 class="payroll-edit-errors-title">请先修正以下内容</h2>
                    <ul class="payroll-edit-errors-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="payroll-edit-card">
                <div class="payroll-edit-topbar">
                    <div class="payroll-edit-batch-meta">
                        <h2 class="payroll-edit-batch-title">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $batch->month_key)->format('Y年n月') }}</h2>
                        <span class="payroll-edit-batch-badge {{ $batch->status === 'imported' ? '' : 'is-muted' }}">{{ $batch->status === 'imported' ? '已解析' : '待上传' }}</span>
                        <span class="payroll-edit-batch-badge is-muted">工资 {{ $recordStats['salary'] }}</span>
                        <span class="payroll-edit-batch-badge is-muted">绩效 {{ $recordStats['performance'] }}</span>
                    </div>
                    <div class="payroll-edit-actions">
                        <button class="button" type="submit" id="payroll-batch-submit">保存并解析</button>
                    </div>
                </div>

                <div class="payroll-edit-upload-grid">
                    <section class="payroll-edit-upload-card" data-upload-card>
                        <div class="payroll-edit-upload-title">
                            <span class="payroll-edit-upload-heading">工资表</span>
                        </div>
                        <div class="payroll-edit-upload-file">
                            当前文件：<strong>{{ $batch->salary_file_name ?: '未上传' }}</strong>
                        </div>
                        <label class="payroll-edit-upload-input">
                            <input type="file" name="salary_file" accept=".xls,.xlsx" data-upload-input>
                            <span class="payroll-edit-upload-placeholder" data-upload-placeholder>选择新的工资表文件（xls / xlsx）</span>
                        </label>
                        <div class="payroll-edit-upload-selected is-empty" data-upload-selected>尚未选择新文件</div>
                        <div class="payroll-edit-upload-error">@error('salary_file'){{ $message }}@enderror</div>
                        @if ($recordStats['salary'] > 0)
                            <div class="payroll-edit-export">
                                <a
                                    class="button secondary payroll-export-button"
                                    href="{{ route('admin.payroll.batches.export', ['batch' => $batch->id, 'type' => 'salary']) }}"
                                    data-loading-text="生成中…"
                                >导出工资</a>
                            </div>
                        @endif
                    </section>

                    <section class="payroll-edit-upload-card" data-upload-card>
                        <div class="payroll-edit-upload-title">
                            <span class="payroll-edit-upload-heading">绩效表</span>
                        </div>
                        <div class="payroll-edit-upload-file">
                            当前文件：<strong>{{ $batch->performance_file_name ?: '未上传' }}</strong>
                        </div>
                        <label class="payroll-edit-upload-input">
                            <input type="file" name="performance_file" accept=".xls,.xlsx" data-upload-input>
                            <span class="payroll-edit-upload-placeholder" data-upload-placeholder>选择新的绩效表文件（xls / xlsx）</span>
                        </label>
                        <div class="payroll-edit-upload-selected is-empty" data-upload-selected>尚未选择新文件</div>
                        <div class="payroll-edit-upload-error">@error('performance_file'){{ $message }}@enderror</div>
                        @if ($recordStats['performance'] > 0)
                            <div class="payroll-edit-export">
                                <a
                                    class="button secondary payroll-export-button"
                                    href="{{ route('admin.payroll.batches.export', ['batch' => $batch->id, 'type' => 'performance']) }}"
                                    data-loading-text="生成中…"
                                >导出绩效</a>
                            </div>
                        @endif
                    </section>
                </div>

                <div class="payroll-edit-submit-status" id="payroll-batch-submit-status" aria-live="polite">
                    <span class="payroll-edit-submit-spinner" aria-hidden="true"></span>
                    <span>正在解析表格，请稍候…</span>
                </div>

                @if (!empty($importSummary) && is_array($importSummary))
                    <section class="payroll-edit-result">
                        <h3 class="payroll-edit-result-title">最近一次解析结果</h3>
                        <div class="payroll-edit-result-grid">
                            @foreach ($importSummary as $type => $summary)
                                <article class="payroll-edit-result-card">
                                    <div class="payroll-edit-result-card-title">{{ $type === 'salary' ? '工资表' : '绩效表' }}</div>
                                    <div class="payroll-edit-result-card-meta">
                                        已匹配 {{ $summary['matched'] ?? 0 }} 位员工
                                        @if (!empty($summary['imported_at']))
                                            ，解析时间 {{ $summary['imported_at'] }}
                                        @endif
                                    </div>

                                    @if (!empty($summary['sheets']) && is_iterable($summary['sheets']))
                                        <div class="payroll-edit-sheet-list">
                                            @foreach ($summary['sheets'] as $sheet)
                                                <div class="payroll-edit-sheet-item">
                                                    <div>
                                                        <div class="payroll-edit-sheet-name">{{ $sheet['name'] ?? '未命名工作表' }}</div>
                                                        <div class="payroll-edit-sheet-mode">
                                                            @if (($sheet['mode'] ?? '') === 'paired')
                                                                并排双表模式
                                                            @elseif (($sheet['mode'] ?? '') === 'persisted')
                                                                当前已解析数据
                                                            @else
                                                                标准横表模式
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <span class="payroll-edit-sheet-count">{{ $sheet['matched'] ?? 0 }} 条</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            </section>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/payroll-admin-batches-edit.js') }}"></script>
@endpush
