@if (session('status'))
    <div class="alert alert-ok" role="status"><x-icon name="check" /><p>{{ session('status') }}</p></div>
@endif

@if ($errors->any())
    <div class="alert alert-bad" role="alert">
        <x-icon name="alert" />
        <div>
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    </div>
@endif
