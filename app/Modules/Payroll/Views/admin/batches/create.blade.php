@extends('layouts.admin')

@section('title', '新增工资批次 - 功能模块 - ' . app(\App\Support\SystemSettings::class)->string('system.name', (string) config('app.name')))
@section('breadcrumb', '后台管理 / 功能模块 / 工资信息 / 新增工资批次')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/payroll-admin-batches-create.css') }}">
@endpush

@section('content')
    <form method="POST" action="{{ route('admin.payroll.batches.store') }}" data-payroll-batch-create-form data-existing-batch-links='@json($existingBatchLinks ?? [])' data-existing-batch-map='@json($existingBatchMap ?? [])' data-has-old-month="{{ old('month_key') ? '1' : '0' }}">
        @csrf

        <div class="payroll-header">
            <div>
                <h1 class="payroll-header-title">新增工资批次</h1>
                <div class="payroll-header-desc">创建月份批次后，再进入下一步上传工资表和绩效表。</div>
            </div>
            <div class="page-header-actions">
                <a class="button secondary" href="{{ route('admin.payroll.batches.index') }}">返回列表</a>
            </div>
        </div>

        @include('payroll::admin._nav')

        <div class="payroll-create-shell">
            @if ($errors->any())
                <section class="payroll-create-errors">
                    <h2 class="payroll-create-errors-title">请先修正以下内容</h2>
                    <ul class="payroll-create-errors-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="payroll-create-card">
                <h2 class="payroll-create-card-title">基础信息</h2>
                <div class="payroll-create-card-desc">这里只需要选择工资月份，创建后即可继续上传工资表和绩效表。</div>

                <div class="payroll-create-form">
                    <label class="payroll-create-field">
                        <span class="payroll-create-field-title">
                            工资月份
                            <span class="payroll-create-pill">必填</span>
                        </span>
                        <input type="hidden" name="month_key" id="payroll-month-key" value="{{ old('month_key') }}">
                        <span class="payroll-create-month-picker @error('month_key') is-invalid @enderror" id="payroll-month-picker">
                            <span class="payroll-month-select" data-payroll-select>
                                <select class="payroll-month-select-native" id="payroll-month-year" aria-label="选择年份">
                                    @php
                                        $oldMonthKey = old('month_key');
                                        $selectedYear = now()->year;
                                        $selectedMonth = now()->month;
                                        if (is_string($oldMonthKey) && preg_match('/^(\d{4})-(\d{2})$/', $oldMonthKey, $monthMatches)) {
                                            $selectedYear = (int) $monthMatches[1];
                                            $selectedMonth = (int) $monthMatches[2];
                                        }
                                    @endphp
                                    @for ($year = now()->year; $year <= now()->year + 1; $year++)
                                        <option value="{{ $year }}" @selected($selectedYear === $year)>{{ $year }} 年</option>
                                    @endfor
                                </select>
                                <button class="payroll-month-select-trigger" type="button" data-payroll-select-trigger aria-haspopup="listbox" aria-expanded="false">
                                    <span data-payroll-select-label>{{ $selectedYear }} 年</span>
                                </button>
                                <div class="payroll-month-select-panel" role="listbox" data-payroll-select-panel>
                                    @for ($year = now()->year; $year <= now()->year + 1; $year++)
                                        <button class="payroll-month-select-option @if($selectedYear === $year) is-active @endif" type="button" data-payroll-select-option data-value="{{ $year }}">
                                            <span>{{ $year }} 年</span>
                                            <span class="payroll-month-select-option-status">已添加</span>
                                            <svg class="payroll-month-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                                        </button>
                                    @endfor
                                </div>
                            </span>
                            <span class="payroll-create-month-separator">·</span>
                            <span class="payroll-month-select" data-payroll-select>
                                <select class="payroll-month-select-native" id="payroll-month-month" aria-label="选择月份">
                                    @for ($month = 1; $month <= 12; $month++)
                                        <option value="{{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }}" @selected($selectedMonth === $month)>{{ $month }} 月</option>
                                    @endfor
                                </select>
                                <button class="payroll-month-select-trigger" type="button" data-payroll-select-trigger aria-haspopup="listbox" aria-expanded="false">
                                    <span data-payroll-select-label>{{ $selectedMonth }} 月</span>
                                </button>
                                <div class="payroll-month-select-panel" role="listbox" data-payroll-select-panel>
                                    @for ($month = 1; $month <= 12; $month++)
                                        <button class="payroll-month-select-option @if($selectedMonth === $month) is-active @endif" type="button" data-payroll-select-option data-value="{{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }}">
                                            <span>{{ $month }} 月</span>
                                            <span class="payroll-month-select-option-status">已添加</span>
                                            <svg class="payroll-month-select-check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5 6.5 11.5 12.5 4.5"/></svg>
                                        </button>
                                    @endfor
                                </div>
                            </span>
                        </span>
                        <div class="payroll-create-error" data-error-for="month_key">@error('month_key'){{ $message }}@enderror</div>
                        <div class="payroll-create-hint" id="payroll-month-existing-hint">
                            <span id="payroll-month-existing-text">该月份批次已存在，请前往编辑页重新上传工资表或绩效表。</span>
                            <a class="payroll-create-hint-link" id="payroll-month-existing-link" href="#">前往编辑该月份</a>
                        </div>
                    </label>

                </div>

                <div class="payroll-create-actions">
                    <button class="button" type="submit">创建批次</button>
                </div>
            </section>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/payroll-admin-batches-create.js') }}"></script>
@endpush
