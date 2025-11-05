import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

type LogItem = {
    id: number;
    action: string;
    user?: string | null;
    key_name?: string | null;
    device?: string | null;
    created_at?: string | null;
};

type Props = {
    logs: LogItem[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Access logs',
        href: '/access-logs',
    },
];

export default function AccessLogs({ logs }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Access logs" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-xl font-medium">Access logs</h1>
                    <p className="text-sm text-neutral-600">Latest access events captured by the system.</p>
                </div>

                <div className="overflow-x-auto rounded-md border border-neutral-200 dark:border-neutral-800">
                    <table className="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-800">
                        <thead className="bg-neutral-50 dark:bg-neutral-900">
                            <tr>
                                <th className="whitespace-nowrap px-3 py-2 text-left font-semibold">Time</th>
                                <th className="whitespace-nowrap px-3 py-2 text-left font-semibold">Action</th>
                                <th className="whitespace-nowrap px-3 py-2 text-left font-semibold">User</th>
                                <th className="whitespace-nowrap px-3 py-2 text-left font-semibold">Key</th>
                                <th className="whitespace-nowrap px-3 py-2 text-left font-semibold">Device</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                            {logs?.length ? (
                                logs.map((log) => (
                                    <tr key={log.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-900">
                                        <td className="px-3 py-2 align-top">
                                            {log.created_at ? new Date(log.created_at).toLocaleString() : '—'}
                                        </td>
                                        <td className="px-3 py-2 align-top">{log.action ?? '—'}</td>
                                        <td className="px-3 py-2 align-top">{log.user ?? '—'}</td>
                                        <td className="px-3 py-2 align-top">{log.key_name ?? '—'}</td>
                                        <td className="px-3 py-2 align-top">{log.device ?? '—'}</td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={5} className="px-3 py-6 text-center text-neutral-600">
                                        No access logs found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}