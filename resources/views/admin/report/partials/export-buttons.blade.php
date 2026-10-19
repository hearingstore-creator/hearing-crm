{{-- Standard export buttons for a report header. $formats: ['csv'] or ['xlsx', 'csv']; keeps the current filters. --}}
@foreach ($formats ?? ['csv'] as $fmt)
    <a href="{{ request()->fullUrlWithQuery(['export' => $fmt]) }}" class="btn btn-secondary btn-sm" id="btn-export-{{ $fmt }}">
        <i class="fas {{ $fmt === 'xlsx' ? 'fa-file-excel' : 'fa-file-csv' }} mr-1"></i>{{ $fmt === 'xlsx' ? 'Excel' : 'CSV' }}
    </a>
@endforeach
