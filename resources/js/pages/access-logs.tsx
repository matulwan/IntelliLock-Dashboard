import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Download, Eye, Clock, User, Shield, AlertTriangle, CheckCircle } from 'lucide-react';
import { motion } from 'framer-motion';
import { useState } from 'react';

interface AccessLog {
    id: number;
    user: string;
    type: string;
    timestamp: string;
    status: string;
    role: string;
    device: string;
    key_name?: string;
    accessed_item: string;
    formatted_time: string;
    time_ago: string;
}

interface Stats {
    totalLogs: number;
    successfulAccess: number;
    failedAccess: number;
    successRate: number;
    todayLogs: number;
}

interface PageProps {
    accessLogs: AccessLog[];
    stats: Stats;
    [key: string]: any;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Access Logs',
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

const cardHover = {
    scale: 1.02,
    transition: { duration: 0.3 },
};

const buttonHover = {
    scale: 1.05,
    transition: { duration: 0.2 },
};

const rowHover = {
    backgroundColor: 'rgba(0, 0, 0, 0.03)',
    transition: { duration: 0.2 },
};

export default function AccessLogs() {
    const { accessLogs, stats } = usePage<PageProps>().props;
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');

    // Filter logs based on search and status
    const filteredLogs = accessLogs.filter(log => {
        const matchesSearch = log.user.toLowerCase().includes(searchTerm.toLowerCase()) || 
                           log.device.toLowerCase().includes(searchTerm.toLowerCase());
        const matchesStatus = statusFilter === 'all' || log.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Access Logs" />
            <motion.div
                initial="hidden"
                animate="show"
                variants={container}
                className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-6"
            >
                {/* Statistics Cards */}
                <motion.div variants={container} className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <motion.div variants={item} whileHover={cardHover}>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Logs</CardTitle>
                                <motion.div whileHover={{ rotate: 10 }}>
                                    <Clock className="h-4 w-4 text-blue-500" />
                                </motion.div>
                            </CardHeader>
                            <CardContent>
                                <motion.div 
                                    className="text-2xl font-bold text-blue-600"
                                    initial={{ scale: 0.9 }}
                                    animate={{ scale: 1 }}
                                    transition={{ type: 'spring', stiffness: 500 }}
                                >
                                    {stats.totalLogs}
                                </motion.div>
                                <p className="text-xs text-muted-foreground">All time records</p>
                            </CardContent>
                        </Card>
                    </motion.div>

                    <motion.div variants={item} whileHover={cardHover}>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Successful</CardTitle>
                                <motion.div whileHover={{ scale: 1.1 }}>
                                    <CheckCircle className="h-4 w-4 text-green-500" />
                                </motion.div>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">{stats.successfulAccess}</div>
                                <p className="text-xs text-muted-foreground">Successful accesses</p>
                            </CardContent>
                        </Card>
                    </motion.div>

                    <motion.div variants={item} whileHover={cardHover}>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Failed</CardTitle>
                                <motion.div whileHover={{ rotate: 10 }}>
                                    <AlertTriangle className="h-4 w-4 text-red-500" />
                                </motion.div>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">{stats.failedAccess}</div>
                                <p className="text-xs text-muted-foreground">Failed attempts</p>
                            </CardContent>
                        </Card>
                    </motion.div>

                    <motion.div variants={item} whileHover={cardHover}>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Success Rate</CardTitle>
                                <motion.div whileHover={{ scale: 1.1 }}>
                                    <Shield className="h-4 w-4 text-purple-500" />
                                </motion.div>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-purple-600">{stats.successRate}%</div>
                                <p className="text-xs text-muted-foreground">Overall success rate</p>
                            </CardContent>
                        </Card>
                    </motion.div>

                    <motion.div variants={item} whileHover={cardHover}>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Today</CardTitle>
                                <motion.div whileHover={{ rotate: 10 }}>
                                    <User className="h-4 w-4 text-orange-500" />
                                </motion.div>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-orange-600">{stats.todayLogs}</div>
                                <p className="text-xs text-muted-foreground">Today's accesses</p>
                            </CardContent>
                        </Card>
                    </motion.div>
                </motion.div>

                {/* Access Logs Table */}
                <motion.div variants={item}>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-semibold">Access Logs</CardTitle>
                            <div className="flex gap-2">
                                <motion.div 
                                    whileHover={{ scale: 1.01 }} 
                                    className="relative flex-1 max-w-xs"
                                >
                                    <input
                                        type="text"
                                        placeholder="Search logs..."
                                        className="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                    />
                                    <User className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                </motion.div>

                                <motion.select
                                    whileHover={{ scale: 1.01 }}
                                    className="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value={statusFilter}
                                    onChange={(e) => setStatusFilter(e.target.value)}
                                >
                                    <option value="all">All Statuses</option>
                                    <option value="success">Successful</option>
                                    <option value="failed">Failed</option>
                                </motion.select>

                                <motion.div whileHover={buttonHover}>
                                    <Button variant="default" size="sm" className="bg-blue-600 hover:bg-blue-700" aria-label="Export access logs">
                                        <Download className="h-4 w-4 mr-2" />
                                        Export
                                    </Button>
                                </motion.div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="rounded-md border overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>User</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Timestamp</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Role</TableHead>
                                            <TableHead>Key/Access</TableHead>
                                            <TableHead>Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {filteredLogs.length > 0 ? (
                                            filteredLogs.map((log, index) => (
                                                <motion.tr
                                                    key={log.id}
                                                    initial={{ opacity: 0, y: 10 }}
                                                    animate={{ opacity: 1, y: 0 }}
                                                    transition={{ delay: index * 0.05, duration: 0.3 }}
                                                    whileHover={rowHover}
                                                    className="transition-colors hover:bg-muted/50"
                                                >
                                                    <TableCell className="font-medium">{log.user}</TableCell>
                                                    <TableCell>
                                                        <motion.div whileHover={{ scale: 1.05 }}>
                                                            <Badge variant={log.type === 'RFID' ? 'outline' : 'secondary'} className="text-xs px-2 py-0.5">
                                                                {log.type}
                                                            </Badge>
                                                        </motion.div>
                                                    </TableCell>
                                                    <TableCell>{log.timestamp}</TableCell>
                                                    <TableCell>
                                                        <motion.div whileHover={{ scale: 1.05 }}>
                                                            <Badge 
                                                                variant={log.status === 'success' ? 'default' : 'destructive'} 
                                                                className="text-xs px-2 py-0.5"
                                                            >
                                                                {log.status}
                                                            </Badge>
                                                        </motion.div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <motion.div whileHover={{ scale: 1.05 }}>
                                                            <Badge
                                                                variant={
                                                                    log.role === 'Student'
                                                                        ? 'secondary'
                                                                        : log.role === 'Lecturer'
                                                                        ? 'default'
                                                                        : 'outline'
                                                                }
                                                                className={
                                                                    log.role === 'Student'
                                                                        ? 'bg-blue-100 text-blue-700 border-blue-200'
                                                                        : log.role === 'Lecturer'
                                                                        ? 'bg-green-100 text-green-700 border-green-200'
                                                                        : 'bg-yellow-100 text-yellow-700 border-yellow-200'
                                                                + ' text-xs px-2 py-0.5'}
                                                            >
                                                                {log.role}
                                                            </Badge>
                                                        </motion.div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <span className="font-medium text-blue-600">
                                                            {log.accessed_item}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell>
                                                        <motion.div whileHover={{ scale: 1.1 }}>
                                                            <Button variant="ghost" size="sm" aria-label={`View details for ${log.user}`}>
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                        </motion.div>
                                                    </TableCell>
                                                </motion.tr>
                                            ))
                                        ) : (
                                            <motion.tr
                                                initial={{ opacity: 0 }}
                                                animate={{ opacity: 1 }}
                                                className="h-24"
                                            >
                                                <TableCell colSpan={7} className="text-center text-muted-foreground">
                                                    No access logs found matching your criteria
                                                </TableCell>
                                            </motion.tr>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                </motion.div>
            </motion.div>
        </AppLayout>
    );
}