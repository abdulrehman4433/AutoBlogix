<x-app-layout title="Logs">
    <div class="space-y-6">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">Activity logs</h1>
            <p class="mt-1 text-sm text-gray-500">
                WordPress connections, publishing attempts, and AI generations — newest first.
            </p>
        </div>

        @php
            $typeLabels = ['connection' => 'Connection', 'publishing' => 'Publishing', 'ai' => 'AI'];
            $typeOptions = $typeLabels;
            $statusOptions = ['success' => 'Success', 'failed' => 'Failed', 'pending' => 'Pending', 'processing' => 'Processing'];
        @endphp

        <form method="GET" action="{{ route('logs.index') }}" class="grid gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-200 sm:grid-cols-4">
            <x-select
                name="type"
                label="Type"
                :options="$typeOptions"
                :selected="$type ?? ''"
                placeholder="All types"
            />
            <x-select
                name="status"
                label="Status"
                :options="$statusOptions"
                :selected="$status ?? ''"
                placeholder="All statuses"
            />
            <div class="flex items-end gap-2">
                <x-button variant="primary" type="submit">Filter</x-button>
                <x-button variant="secondary" :href="route('logs.index')">Reset</x-button>
            </div>
        </form>

        <x-table :columns="5" :isEmpty="$rows->isEmpty()">
            <x-slot name="head">
                <th scope="col">Time</th>
                <th scope="col">Type</th>
                <th scope="col">Subject</th>
                <th scope="col">Status</th>
                <th scope="col">Detail</th>
            </x-slot>

            <x-slot name="body">
                @foreach ($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap text-sm text-gray-600">
                            {{ $row->time_human }}
                            <span class="block text-xs text-gray-400">{{ $row->time_exact }}</span>
                        </td>
                        <td class="whitespace-nowrap">
                            <x-badge variant="gray">{{ $typeLabels[$row->type] ?? $row->type }}</x-badge>
                            <span class="mt-1 block text-xs text-gray-400">{{ $row->kind_label }}</span>
                        </td>
                        <td class="max-w-xs">
                            @if ($row->subject_url !== null)
                                <a class="block truncate font-medium text-gray-900 hover:text-indigo-600" href="{{ $row->subject_url }}">
                                    {{ $row->subject_label }}
                                </a>
                            @else
                                <span class="text-gray-400">{{ $row->subject_label }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            <x-badge :variant="$row->status_badge">{{ $row->status_label }}</x-badge>
                        </td>
                        <td class="max-w-md">
                            @if ($row->detail !== '')
                                <span class="block truncate text-gray-600" title="{{ $row->detail }}">{{ $row->detail }}</span>
                            @else
                                <span class="text-gray-400">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-slot>

            <x-slot name="empty">
                @if (request()->hasAny(['type', 'status']))
                    <p class="font-medium text-gray-900">No logs match your filters.</p>
                    <p class="mt-1">Try a different type or status, or reset the filters.</p>
                    <p class="mt-4">
                        <x-button variant="secondary" :href="route('logs.index')">Reset filters</x-button>
                    </p>
                @else
                    <p class="font-medium text-gray-900">No activity yet.</p>
                    <p class="mt-1">Connections, publishing attempts, and AI generations appear here as they happen.</p>
                @endif
            </x-slot>
        </x-table>

        @if ($rows->hasPages())
            <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-gray-200">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
