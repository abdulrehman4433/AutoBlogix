@props([
    'columns' => 1,
    'isEmpty' => false,
])

<div {{ $attributes->class(['overflow-x-auto rounded-lg ring-1 ring-gray-200']) }}>
    <table class="abx-table min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                @isset($head)
                    {{ $head }}
                @else
                    <th scope="col">Column</th>
                @endisset
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
            @if ($isEmpty)
                <tr>
                    <td colspan="{{ $columns }}" class="!py-10 text-center text-sm text-gray-500">
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
                    <td colspan="{{ $columns }}" class="text-gray-500">Row</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
