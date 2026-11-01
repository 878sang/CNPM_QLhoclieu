@include('layouts._shell', ['role' => auth()->user()->role?->name ?? 'student'])
