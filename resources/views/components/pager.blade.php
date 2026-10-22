@props(['paginator'])
@if ($paginator->hasPages())
    <div class="pagination">
        <span>Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
        <div class="pages">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm" aria-disabled="true" style="opacity:.5">Previous</span>
            @else
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}">Previous</a>
            @endif
            @if ($paginator->hasMorePages())
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}">Next</a>
            @else
                <span class="btn btn-secondary btn-sm" aria-disabled="true" style="opacity:.5">Next</span>
            @endif
        </div>
    </div>
@endif
