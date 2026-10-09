@props(['status'])

<x-badge :color="$status->color()" {{ $attributes }}>{{ strtoupper($status->label()) }}</x-badge>
