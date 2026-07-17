@php($title ??= null)
<x-core::app-shell :title="$title">
    {{ $slot }}
</x-core::app-shell>