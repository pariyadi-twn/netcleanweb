@extends('layouts.app')

@section('title', 'Impor data - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Laporan" title="Impor data" description="Unggah CSV pelanggan atau katalog. Gunakan dry run untuk memeriksa hasil tanpa menulis data." />
    @if ($errors->any())<x-ui.alert tone="danger" class="mb-5"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif
    <form method="POST" action="{{ route('reports.imports.store') }}" enctype="multipart/form-data" class="grid gap-3 rounded-container border border-divider bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        @csrf
        <select name="type" class="min-h-11 rounded-control border border-input px-3 text-sm"><option value="customers">Pelanggan</option><option value="catalog">Katalog</option></select>
        <input name="file" type="file" accept=".csv,text/csv,.txt" required class="min-h-11 rounded-control border border-input px-3 py-2 text-sm">
        <label class="flex min-h-11 items-center gap-2 text-sm text-muted"><input type="checkbox" name="dry_run" value="1" class="size-4 rounded border-input"> Dry run</label>
        <button class="min-h-11 rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">Unggah</button>
    </form>
    <section class="mt-5 rounded-container border border-divider bg-white p-5 shadow-sm">
        <h2 class="font-extrabold">Riwayat impor</h2>
        <div class="mt-3 divide-y divide-divider">
            @forelse($batches as $batch)
                <div class="flex flex-wrap justify-between gap-2 py-3 text-sm"><span class="font-semibold">{{ $batch->file_name }} · {{ $batch->type }}</span><span class="text-muted">{{ $batch->status->value }} · {{ $batch->imported_rows }}/{{ $batch->total_rows }} baris · {{ $batch->created_at?->format('d M Y H:i') }}</span></div>
            @empty
                <p class="py-4 text-sm text-muted">Belum ada impor.</p>
            @endforelse
        </div>
    </section>
@endsection
