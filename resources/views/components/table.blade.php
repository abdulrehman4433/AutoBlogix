@props([
    'columns' => 1,
    'isEmpty' => false,
])

<div {{ $attributes->class(['table-wrap overflow-x-auto']) }}>
    <table class="table min-w-full text-sm">
        <thead>
            <tr>
                @isset($head)
                    {{ $head }}
                @else
                    <th scope="col">Column</th>
                @endisset
            </tr>
        </thead>
        <tbody>
            @if ($isEmpty)
                <tr>
                    <td colspan="{{ $columns }}" class="!py-10 text-center text-sm text-ink-muted">
                        @isset($empty)
                            {{ $empty }}
                        @else
                            No records found.
                        @endisset
                    </td>
                </tr>
            @elseif (isset($body))
                {{ $body }}
            @else
                <tr>
                    <td colspan="{{ $columns }}" class="text-ink-muted">Row</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
