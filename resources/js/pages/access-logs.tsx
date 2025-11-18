import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { motion } from 'framer-motion';
import { Clock, User, Key, Cpu, Activity, Search } from 'lucide-react';
import { useState, useMemo } from 'react';

type LogItem = {
    id: number;
    action: string;
    status: string;
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

// Animation variants
const container = {
    hidden: { opacity: 0 },
    show: {
        opacity: 1,
        transition: {
            staggerChildren: 0.1,
        },
    },
};

const item = {
    hidden: { opacity: 0, y: 20 },
    show: { opacity: 1, y: 0, transition: { duration: 0.5 } },
};

const tableRow = {
    hidden: { opacity: 0, x: -20 },
    show: { opacity: 1, x: 0, transition: { duration: 0.4 } },
};

const iconHover = {
    scale: 1.1,
    rotate: 5,
    transition: { duration: 0.2 },
};

export default function AccessLogs({ logs }: Props) {
    const [searchTerm, setSearchTerm] = useState('');

    // Filter logs based on search criteria
    const filteredLogs = useMemo(() => {
        return logs.filter(log => {
            return searchTerm === '' || 
                log.user?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                log.key_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                log.device?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                log.action?.toLowerCase().includes(searchTerm.toLowerCase());
        });
    }, [logs, searchTerm]);

    // Format action for display
    const formatAction = (action: string) => {
        const actionMap: { [key: string]: string } = {
            'key_taken': 'Key Taken',
            'key_returned': 'Key Returned',
            'key_detected': 'Key Detected',
            'door_unlocked': 'Door Unlocked',
            'door_locked': 'Door Locked',
            'access_denied': 'Access Denied',
            'camera_result': 'Camera Result',
        };
        
        return actionMap[action] || action.replace(/_/g, ' ');
    };

    // Get status display text
    const getStatusText = (action: string, status: string) => {
        if (action === 'key_taken') return 'Key Taken';
        if (action === 'key_returned') return 'Key Returned';
        return status?.charAt(0).toUpperCase() + status?.slice(1) || 'Success';
    };

    // Get status badge color
    const getStatusColor = (action: string, status: string) => {
        if (action === 'key_taken') {
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300';
        }
        if (action === 'key_returned') {
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        }
        if (status === 'success') {
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        }
        if (status === 'denied') {
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        }
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Access logs" />

            <motion.div
                initial="hidden"
                animate="show"
                variants={container}
                className="flex h-full flex-1 flex-col gap-6 p-6"
            >
                {/* Header Section */}
                <motion.div 
                    variants={item}
                    className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <motion.div
                        initial={{ x: -20 }}
                        animate={{ x: 0 }}
                        transition={{ type: 'spring', stiffness: 300 }}
                    >
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-white">Access Logs</h1>
                        <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Monitor and track all key access events in real-time
                        </p>
                    </motion.div>
                </motion.div>

                {/* Search Section */}
                <motion.div variants={item} className="flex flex-col sm:flex-row gap-4">
                    <motion.div 
                        className="flex-1 relative"
                        whileHover={{ scale: 1.01 }}
                    >
                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search users, keys, devices, or actions..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full pl-10 pr-4 py-3 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                        />
                    </motion.div>
                </motion.div>

                {/* Logs Table */}
                <motion.div variants={item} className="flex-1 flex flex-col">
                    <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm flex-1 flex flex-col overflow-hidden">
                        {/* Table Header */}
                        <div className="p-6 pb-4 border-b border-gray-200 dark:border-gray-700">
                            <motion.h2 
                                className="text-lg font-semibold flex items-center gap-2 text-gray-900 dark:text-white"
                                initial={{ x: -10 }}
                                animate={{ x: 0 }}
                                transition={{ type: 'spring', stiffness: 300 }}
                            >
                                <motion.div whileHover={iconHover}>
                                    <Clock className="h-5 w-5 text-blue-600" />
                                </motion.div>
                                Key Access Events
                                <span className="text-sm font-normal text-gray-500 dark:text-gray-400 ml-2">
                                    ({filteredLogs.length} results)
                                </span>
                            </motion.h2>
                        </div>
                        
                        {/* Table Container - Fixed height with proper scrolling */}
                        <div className="flex-1 overflow-hidden">
                            <div className="h-full overflow-auto custom-scrollbar">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead className="bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">
                                        <tr>
                                            <th className="sticky top-0 bg-gray-50 dark:bg-gray-900 px-6 py-4 text-left font-semibold text-gray-900 dark:text-white z-20">
                                                <motion.div className="flex items-center gap-2" whileHover={{ x: 2 }}>
                                                    <Clock className="h-4 w-4" />
                                                    Time
                                                </motion.div>
                                            </th>
                                            <th className="sticky top-0 bg-gray-50 dark:bg-gray-900 px-6 py-4 text-left font-semibold text-gray-900 dark:text-white z-20">
                                                <motion.div className="flex items-center gap-2" whileHover={{ x: 2 }}>
                                                    <Activity className="h-4 w-4" />
                                                    Action & Status
                                                </motion.div>
                                            </th>
                                            <th className="sticky top-0 bg-gray-50 dark:bg-gray-900 px-6 py-4 text-left font-semibold text-gray-900 dark:text-white z-20">
                                                <motion.div className="flex items-center gap-2" whileHover={{ x: 2 }}>
                                                    <User className="h-4 w-4" />
                                                    User
                                                </motion.div>
                                            </th>
                                            <th className="sticky top-0 bg-gray-50 dark:bg-gray-900 px-6 py-4 text-left font-semibold text-gray-900 dark:text-white z-20">
                                                <motion.div className="flex items-center gap-2" whileHover={{ x: 2 }}>
                                                    <Key className="h-4 w-4" />
                                                    Key
                                                </motion.div>
                                            </th>
                                            <th className="sticky top-0 bg-gray-50 dark:bg-gray-900 px-6 py-4 text-left font-semibold text-gray-900 dark:text-white z-20">
                                                <motion.div className="flex items-center gap-2" whileHover={{ x: 2 }}>
                                                    <Cpu className="h-4 w-4" />
                                                    Device
                                                </motion.div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <motion.tbody 
                                        className="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800"
                                        variants={container}
                                        initial="hidden"
                                        animate="show"
                                    >
                                        {filteredLogs?.length ? (
                                            filteredLogs.map((log, index) => (
                                                <motion.tr 
                                                    key={log.id} 
                                                    variants={tableRow}
                                                    transition={{ delay: index * 0.03 }}
                                                    className="group hover:bg-gray-50 dark:hover:bg-gray-900/50 cursor-pointer transition-colors duration-200"
                                                    whileHover={{ 
                                                        backgroundColor: "rgba(0,0,0,0.02)",
                                                    }}
                                                >
                                                    <td className="px-6 py-4 align-top">
                                                        <motion.div 
                                                            className="flex flex-col"
                                                            whileHover={{ x: 2 }}
                                                        >
                                                            <span className="font-medium text-gray-900 dark:text-white text-sm">
                                                                {log.created_at ? new Date(log.created_at).toLocaleDateString() : '—'}
                                                            </span>
                                                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                                                {log.created_at ? new Date(log.created_at).toLocaleTimeString() : '—'}
                                                            </span>
                                                        </motion.div>
                                                    </td>
                                                    <td className="px-6 py-4 align-top">
                                                        <div className="flex flex-col gap-1">
                                                            <motion.span 
                                                                className="text-sm font-medium text-gray-900 dark:text-white capitalize"
                                                                whileHover={{ x: 2 }}
                                                            >
                                                                {formatAction(log.action ?? '')}
                                                            </motion.span>
                                                            <motion.span 
                                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium w-fit ${getStatusColor(log.action ?? '', log.status ?? '')}`}
                                                                whileHover={{ scale: 1.05 }}
                                                            >
                                                                {getStatusText(log.action ?? '', log.status ?? '')}
                                                            </motion.span>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 align-top">
                                                        <motion.div 
                                                            className="flex items-center gap-2"
                                                            whileHover={{ x: 2 }}
                                                        >
                                                            <User className="h-4 w-4 text-gray-400" />
                                                            <span className="text-gray-700 dark:text-gray-300">
                                                                {log.user ?? '—'}
                                                            </span>
                                                        </motion.div>
                                                    </td>
                                                    <td className="px-6 py-4 align-top">
                                                        <motion.div 
                                                            className="flex items-center gap-2"
                                                            whileHover={{ x: 2 }}
                                                        >
                                                            <Key className="h-4 w-4 text-gray-400" />
                                                            <span className="text-gray-700 dark:text-gray-300 font-mono text-sm">
                                                                {log.key_name ?? '—'}
                                                            </span>
                                                        </motion.div>
                                                    </td>
                                                    <td className="px-6 py-4 align-top">
                                                        <motion.div 
                                                            className="flex items-center gap-2"
                                                            whileHover={{ x: 2 }}
                                                        >
                                                            <Cpu className="h-4 w-4 text-gray-400" />
                                                            <span className="text-gray-700 dark:text-gray-300">
                                                                {log.device ?? '—'}
                                                            </span>
                                                        </motion.div>
                                                    </td>
                                                </motion.tr>
                                            ))
                                        ) : (
                                            <motion.tr variants={tableRow}>
                                                <td colSpan={5} className="px-6 py-12 text-center">
                                                    <motion.div 
                                                        className="flex flex-col items-center gap-3"
                                                        initial={{ opacity: 0 }}
                                                        animate={{ opacity: 1 }}
                                                        transition={{ delay: 0.3 }}
                                                    >
                                                        <Activity className="h-16 w-16 text-gray-300 dark:text-gray-600" />
                                                        <div className="text-lg font-medium text-gray-900 dark:text-white">
                                                            No access logs found
                                                        </div>
                                                        <p className="text-sm text-gray-500 dark:text-gray-400 max-w-md">
                                                            {searchTerm 
                                                                ? 'Try adjusting your search criteria.'
                                                                : 'Key access events will appear here once recorded.'
                                                            }
                                                        </p>
                                                    </motion.div>
                                                </td>
                                            </motion.tr>
                                        )}
                                    </motion.tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </motion.div>
            </motion.div>

            <style>{`
                .custom-scrollbar {
                    scrollbar-width: thin;
                    scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
                }
                
                .custom-scrollbar::-webkit-scrollbar {
                    width: 6px;
                    height: 6px;
                }
                
                .custom-scrollbar::-webkit-scrollbar-track {
                    background: transparent;
                    border-radius: 3px;
                }
                
                .custom-scrollbar::-webkit-scrollbar-thumb {
                    background: rgba(156, 163, 175, 0.5);
                    border-radius: 3px;
                }
                
                .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                    background: rgba(156, 163, 175, 0.7);
                }
                
                /* Hide scrollbar when not hovering */
                .custom-scrollbar {
                    scrollbar-width: none;
                }
                
                .custom-scrollbar::-webkit-scrollbar {
                    display: none;
                }
                
                .custom-scrollbar:hover {
                    scrollbar-width: thin;
                }
                
                .custom-scrollbar:hover::-webkit-scrollbar {
                    display: block;
                }
            `}</style>
        </AppLayout>
    );
}