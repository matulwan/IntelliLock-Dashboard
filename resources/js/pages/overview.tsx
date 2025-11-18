import React from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Users, Video, Wifi, WifiOff, Lock, CheckCircle, XCircle, Shield, Fingerprint, CreditCard, DoorOpen, Key, Clock, TrendingUp, Camera, CameraOff } from 'lucide-react';
import { motion } from 'framer-motion';

interface SecuritySnap {
    id: number;
    url: string;
    time: string;
    time_ago: string;
}

interface KeyBox {
    status: string;
    location: string;
    last_seen: string;
    ip_address: string;
    wifi_strength: number | null;
    uptime: string;
    camera_status?: string;
}

interface Stats {
  keys: {
    total: number;
    available: number;
    checked_out: number;
  };
  users: {
    total: number;
    iot_enabled: number;
    active: number;
  };
  access: {
    total: number;
    successful: number;
    failed: number;
    today: number;
    success_rate: number;
  };
  access_by_type: {
    rfid: number;
    fingerprint: number;
    remote: number;
  };
}

interface Activity {
  id: number;
  user: string;
  type: string;
  status: string;
  device: string;
  timestamp: string;
  time_ago: string;
}

interface KeyTransaction {
  id: number;
  key_name: string;
  user_name: string;
  action: string;
  transaction_time: string;
  time_ago: string;
}

interface WeeklyData {
  date: string;
  count: number;
}

interface Props {
  keyBox: KeyBox;
  stats: Stats;
  recentActivity: Activity[];
  recentKeyTransactions: KeyTransaction[];
  weeklyData: WeeklyData[];
  latestSecuritySnap: SecuritySnap | null;
  cameraStatus: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Overview',
        href: '/overview',
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

const iconHover = {
    scale: 1.1,
    rotate: 5,
    transition: { duration: 0.2 },
};

// Function to get camera status
const getCameraStatus = (cameraStatus: string): { status: string; color: string; icon: React.ReactNode; description: string } => {
    const isOnline = cameraStatus === 'online';
    
    return {
        status: isOnline ? 'Online' : 'Offline',
        color: isOnline ? 'text-green-600' : 'text-red-600',
        icon: isOnline ? <Camera className="h-4 w-4 text-green-600" /> : <CameraOff className="h-4 w-4 text-red-600" />,
        description: isOnline ? 'Live feed active' : 'Camera disconnected'
    };
};

// Function to process latest unlock and replace AUTO_RETURN with real name
const processLatestUnlock = (activity: Activity[], transactions: KeyTransaction[]): Activity | null => {
    if (!activity || activity.length === 0) return null;
    
    const latestActivity = activity[0];
    
    // If the latest activity is AUTO_RETURN, try to find the real user
    if (latestActivity.user === 'AUTO_RETURN' || latestActivity.user === 'AUTO_RETURNED') {
        // Look for the most recent key transaction to find who actually took the key
        const latestCheckout = transactions.find(t => t.action === 'checkout');
        if (latestCheckout) {
            return {
                ...latestActivity,
                user: latestCheckout.user_name
            };
        }
        
        // If no transaction found, look for the previous non-AUTO_RETURN activity
        const previousActivity = activity.find(a => a.user !== 'AUTO_RETURN' && a.user !== 'AUTO_RETURNED');
        if (previousActivity) {
            return {
                ...latestActivity,
                user: previousActivity.user
            };
        }
    }
    
    return latestActivity;
};

// Function to get security snap data with fallback
const getSecuritySnap = (latestSnap: SecuritySnap | null) => {
    if (latestSnap && latestSnap.url) {
        return latestSnap;
    }
    
    // Return a placeholder if no snap available
    return null;
};

export default function Overview({ 
    keyBox: initialKeyBox, 
    stats: initialStats, 
    recentActivity: initialActivity, 
    recentKeyTransactions: initialTransactions, 
    weeklyData: initialWeeklyData,
    latestSecuritySnap,
    cameraStatus = 'offline'
}: Props) {
    const [keyBox, setKeyBox] = React.useState(initialKeyBox);
    const [stats, setStats] = React.useState(initialStats);
    const [recentActivity, setRecentActivity] = React.useState(initialActivity);
    const [recentKeyTransactions, setRecentKeyTransactions] = React.useState(initialTransactions);
    const [weeklyData, setWeeklyData] = React.useState(initialWeeklyData);

    React.useEffect(() => {
        // Listen for real-time updates
        const channel = window.Echo.channel('dashboard');
        channel.listen('DashboardDataUpdated', (e: any) => {
            const data = e.data;
            setKeyBox(data.keyBox);
            setStats(data.stats);
            if (data.recentActivity) setRecentActivity(data.recentActivity);
            if (data.recentKeyTransactions) setRecentKeyTransactions(data.recentKeyTransactions);
            if (data.weeklyData) setWeeklyData(data.weeklyData);
        });

        return () => {
            channel.stopListening('DashboardDataUpdated');
        };
    }, []);

    // Process latest unlock to replace AUTO_RETURN
    const processedLatestUnlock = processLatestUnlock(recentActivity, recentKeyTransactions);
    
    // Get camera status - use prop first, then fallback to keyBox.camera_status
    const actualCameraStatus = cameraStatus || keyBox.camera_status || 'offline';
    const cameraStatusInfo = getCameraStatus(actualCameraStatus);
    
    // Get security snap
    const securitySnap = getSecuritySnap(latestSecuritySnap);

    // Use real weekly data from props
    const chartHeight = 220;
    const chartWidth = 700;
    const padding = 40;
    const graphHeight = chartHeight - (padding * 2);
    const graphWidth = chartWidth - (padding * 2);
    const series = (weeklyData && weeklyData.length > 0) ? weeklyData : [{ date: '', count: 0 }];
    const maxValue = Math.max(...series.map(d => d.count));
    const minValue = Math.min(...series.map(d => d.count));
    const range = Math.max(1, maxValue - minValue);
    const generateLinePath = () => {
        const points = series.map((data, index) => {
            const denom = Math.max(1, (series.length - 1));
            const x = padding + (index * (graphWidth / denom));
            const y = padding + graphHeight - ((data.count - minValue) / range) * graphHeight;
            return `${x},${y}`;
        });
        return `M ${points.join(' L ')}`;
    };

    // Use real data from backend
    const latestUnlock = processedLatestUnlock ? {
        name: processedLatestUnlock.user,
        time: processedLatestUnlock.timestamp,
        type: processedLatestUnlock.type === 'fingerprint' ? 'Biometric' : 'RFID',
    } : null;

    const latestKeyTaken = recentKeyTransactions && recentKeyTransactions.length > 0 ? {
        room: recentKeyTransactions[0].key_name,
        number: recentKeyTransactions[0].id,
        time: recentKeyTransactions[0].transaction_time,
    } : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <motion.div
                initial="hidden"
                animate="show"
                variants={container}
                className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6"
            >
                <motion.div 
                    variants={item}
                    className="flex items-center justify-between"
                >
                    <motion.h1 
                        className="text-3xl font-bold"
                        initial={{ x: -20 }}
                        animate={{ x: 0 }}
                        transition={{ type: 'spring', stiffness: 300 }}
                    >
                        Intelli-Lock Dashboard
                    </motion.h1>
                    <motion.div whileHover={{ scale: 1.05 }}>
                        <Badge variant="outline" className="text-sm">
                            Welcome Back!
                        </Badge>
                    </motion.div>
                </motion.div>

                {/* Status Cards Row */}
                <motion.div variants={container} className="grid gap-6 md:grid-cols-3">
                    {/* Latest Unlock Card */}
                    <motion.div variants={item} whileHover={cardHover}>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="flex items-center gap-2">
                                    <motion.div whileHover={iconHover}>
                                        <Lock className="h-5 w-5 text-blue-600" />
                                    </motion.div>
                                    Latest Unlock
                                </CardTitle>
                                {latestUnlock && <span className="text-xs text-muted-foreground">{latestUnlock.time}</span>}
                            </CardHeader>
                            <CardContent className="flex items-center gap-4">
                                {latestUnlock ? (
                                    <>
                                        <motion.div 
                                            className="flex items-center gap-3"
                                            whileHover={{ scale: 1.01 }}
                                        >
                                            <motion.div 
                                                className="rounded-full bg-blue-100 p-2"
                                                whileHover={{ rotate: 10 }}
                                            >
                                                <Users className="h-6 w-6 text-blue-600" />
                                            </motion.div>
                                            <div>
                                                <motion.div 
                                                    className="font-semibold text-lg"
                                                    whileHover={{ x: 2 }}
                                                >
                                                    {latestUnlock.name}
                                                </motion.div>
                                                <div className="text-xs text-muted-foreground">Unlocked the Intelli-Lock</div>
                                            </div>
                                        </motion.div>
                                        <motion.div 
                                            className="ml-auto flex items-center gap-2"
                                            whileHover={{ scale: 1.05 }}
                                        >
                                            {latestUnlock.type === 'Biometric' ? (
                                                <Fingerprint className="h-5 w-5 text-purple-600" />
                                            ) : (
                                                <CreditCard className="h-5 w-5 text-green-600" />
                                            )}
                                            <span className="text-sm font-medium capitalize">{latestUnlock.type}</span>
                                        </motion.div>
                                    </>
                                ) : (
                                    <div className="flex items-center gap-3 text-muted-foreground">
                                        <Lock className="h-6 w-6" />
                                        <div>
                                            <div className="font-medium">No unlock events yet</div>
                                            <div className="text-xs">Waiting for ESP32 data...</div>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </motion.div>

                    {/* Latest Key Taken Card */}
                    <motion.div variants={item} whileHover={cardHover}>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="flex items-center gap-2">
                                    <motion.div whileHover={iconHover}>
                                        <Key className="h-5 w-5 text-yellow-600" />
                                    </motion.div>
                                    Latest Key Taken
                                </CardTitle>
                                {latestKeyTaken && <span className="text-xs text-muted-foreground">{latestKeyTaken.time}</span>}
                            </CardHeader>
                            <CardContent className="flex items-center gap-4">
                                {latestKeyTaken ? (
                                    <motion.div 
                                        className="flex items-center gap-3"
                                        whileHover={{ scale: 1.01 }}
                                    >
                                        <motion.div 
                                            className="rounded-full bg-yellow-100 p-2"
                                            whileHover={{ rotate: 10 }}
                                        >
                                            <DoorOpen className="h-6 w-6 text-yellow-600" />
                                        </motion.div>
                                        <div>
                                            <motion.div 
                                                className="font-semibold text-lg"
                                                whileHover={{ x: 2 }}
                                            >
                                                {latestKeyTaken.room}
                                            </motion.div>
                                            <div className="text-xs text-muted-foreground">Transaction #{latestKeyTaken.number}</div>
                                        </div>
                                    </motion.div>
                                ) : (
                                    <div className="flex items-center gap-3 text-muted-foreground">
                                        <Key className="h-6 w-6" />
                                        <div>
                                            <div className="font-medium">No key transactions yet</div>
                                            <div className="text-xs">Waiting for ESP32 data...</div>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </motion.div>

                    {/* Latest Security Snap Card */}
                    <motion.div variants={item} whileHover={cardHover}>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="flex items-center gap-2">
                                    <motion.div whileHover={iconHover}>
                                        <Video className="h-5 w-5 text-blue-600" />
                                    </motion.div>
                                    Latest Security Snap
                                </CardTitle>
                                {securitySnap && <span className="text-xs text-muted-foreground">{securitySnap.time}</span>}
                            </CardHeader>
                            <CardContent className="flex items-center gap-4">
                                {securitySnap ? (
                                    <>
                                        <motion.div 
                                            whileHover={{ scale: 1.05 }}
                                            className="relative"
                                        >
                                            <img 
                                                src={securitySnap.url} 
                                                alt="Latest Security Snap" 
                                                className="w-32 h-24 object-cover rounded border" 
                                                onError={(e) => {
                                                    // If image fails to load, show placeholder
                                                    e.currentTarget.style.display = 'none';
                                                }}
                                            />
                                            <motion.div 
                                                className="absolute inset-0 bg-black/20 opacity-0 hover:opacity-100 flex items-center justify-center transition-opacity"
                                                whileHover={{ opacity: 1 }}
                                            >
                                                <motion.button 
                                                    className="bg-white/90 text-black px-3 py-1 rounded-full text-xs font-medium shadow"
                                                    whileHover={{ scale: 1.1 }}
                                                    onClick={() => window.open(securitySnap.url, '_blank')}
                                                >
                                                    View Full Size
                                                </motion.button>
                                            </motion.div>
                                        </motion.div>
                                        <div className="flex flex-col gap-1">
                                            <span className="text-xs text-muted-foreground">Most recent snapshot from ESP32 cam</span>
                                            <span className="text-xs text-green-600">{securitySnap.time_ago}</span>
                                        </div>
                                    </>
                                ) : (
                                    <div className="flex items-center gap-3 text-muted-foreground">
                                        <Video className="h-6 w-6" />
                                        <div>
                                            <div className="font-medium">No security snaps available</div>
                                            <div className="text-xs">-</div>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </motion.div>
                </motion.div>

                {/* Quick Stats Bar */}
                <motion.div variants={container} className="grid gap-6 md:grid-cols-3">
                    {/* Total Users */}
                    <motion.div variants={item} whileHover={cardHover}>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Users</CardTitle>
                                <motion.div whileHover={iconHover}>
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                </motion.div>
                            </CardHeader>
                            <CardContent>
                                <motion.div 
                                    className="text-2xl font-bold"
                                    initial={{ scale: 0.9 }}
                                    animate={{ scale: 1 }}
                                    transition={{ type: 'spring', stiffness: 500 }}
                                >
                                    {stats.users.total}
                                </motion.div>
                                <p className="text-xs text-muted-foreground">Students, Lecturers & Staff</p>
                            </CardContent>
                        </Card>
                    </motion.div>
                    
                    {/* Total Accesses */}
                    <motion.div variants={item} whileHover={cardHover}>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Accesses</CardTitle>
                                <motion.div whileHover={iconHover}>
                                    <CheckCircle className="h-4 w-4 text-muted-foreground" />
                                </motion.div>
                            </CardHeader>
                            <CardContent>
                                <motion.div 
                                    className="text-2xl font-bold text-blue-600"
                                    initial={{ scale: 0.9 }}
                                    animate={{ scale: 1 }}
                                    transition={{ type: 'spring', stiffness: 500 }}
                                >
                                    {stats.access.total}
                                </motion.div>
                                <p className="text-xs text-muted-foreground">Successful + Failed entries today</p>
                            </CardContent>
                        </Card>
                    </motion.div>
                    
                    {/* Camera Status */}
                    <motion.div variants={item} whileHover={cardHover}>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Camera Status</CardTitle>
                                <motion.div whileHover={iconHover}>
                                    {cameraStatusInfo.icon}
                                </motion.div>
                            </CardHeader>
                            <CardContent>
                                <motion.div 
                                    className={`text-2xl font-bold ${cameraStatusInfo.color}`}
                                    initial={{ scale: 0.9 }}
                                    animate={{ scale: 1 }}
                                    transition={{ type: 'spring', stiffness: 500 }}
                                >
                                    {cameraStatusInfo.status}
                                </motion.div>
                                <p className="text-xs text-muted-foreground">{cameraStatusInfo.description}</p>
                            </CardContent>
                        </Card>
                    </motion.div>
                </motion.div>

                {/* Rest of your component remains the same */}
                <motion.div variants={item}>
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <motion.div whileHover={iconHover}>
                                    <CheckCircle className="h-5 w-5" />
                                </motion.div>
                                Access Frequency per Day (Last 7 Days)
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-center">
                                <svg width={chartWidth} height={chartHeight} className="overflow-visible">
                                    {/* Grid lines */}
                                    {[0, 1, 2, 3, 4].map((i) => (
                                        <motion.line
                                            key={i}
                                            x1={padding}
                                            y1={padding + (i * graphHeight / 4)}
                                            x2={padding + graphWidth}
                                            y2={padding + (i * graphHeight / 4)}
                                            stroke="#e5e7eb"
                                            strokeWidth="1"
                                            strokeDasharray="2,2"
                                            initial={{ opacity: 0 }}
                                            animate={{ opacity: 1 }}
                                            transition={{ delay: 0.2 + (i * 0.05) }}
                                        />
                                    ))}
                                    {/* Y-axis labels */}
                                    {[0, 1, 2, 3, 4].map((i) => {
                                        const value = Math.round(maxValue - (i * (maxValue - minValue) / 4));
                                        return (
                                            <motion.text
                                                key={i}
                                                x={padding - 12}
                                                y={padding + (i * graphHeight / 4) + 5}
                                                textAnchor="end"
                                                className="text-xs fill-muted-foreground"
                                                initial={{ opacity: 0 }}
                                                animate={{ opacity: 1 }}
                                                transition={{ delay: 0.3 + (i * 0.05) }}
                                            >
                                                {value}
                                            </motion.text>
                                        );
                                    })}
                                    {/* Line */}
                                    <motion.path
                                        d={generateLinePath()}
                                        stroke="#3b82f6"
                                        strokeWidth="3"
                                        fill="none"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        initial={{ pathLength: 0 }}
                                        animate={{ pathLength: 1 }}
                                        transition={{ duration: 1, ease: "easeInOut" }}
                                    />
                                    {/* Data points */}
                                    {series.map((data, index) => {
                                        const denom = Math.max(1, (series.length - 1));
                                        const x = padding + (index * (graphWidth / denom));
                                        const y = padding + graphHeight - ((data.count - minValue) / range) * graphHeight;
                                        return (
                                            <motion.g 
                                                key={index}
                                                initial={{ opacity: 0, scale: 0 }}
                                                animate={{ opacity: 1, scale: 1 }}
                                                transition={{ delay: 0.5 + (index * 0.1) }}
                                            >
                                                <circle
                                                    cx={x}
                                                    cy={y}
                                                    r="5"
                                                    fill="#3b82f6"
                                                    stroke="white"
                                                    strokeWidth="2"
                                                />
                                                <text
                                                    x={x}
                                                    y={y - 10}
                                                    textAnchor="middle"
                                                    className="text-xs font-medium fill-blue-700"
                                                >
                                                    {data.count}
                                                </text>
                                            </motion.g>
                                        );
                                    })}
                                    {/* X-axis labels */}
                                    {series.map((data, index) => {
                                        const denom = Math.max(1, (series.length - 1));
                                        const x = padding + (index * (graphWidth / denom));
                                        return (
                                            <motion.text
                                                key={index}
                                                x={x}
                                                y={chartHeight - padding + 18}
                                                textAnchor="middle"
                                                className="text-xs fill-muted-foreground"
                                                initial={{ opacity: 0, y: chartHeight - padding + 28 }}
                                                animate={{ opacity: 1, y: chartHeight - padding + 18 }}
                                                transition={{ delay: 0.7 + (index * 0.05) }}
                                            >
                                                {data.date}
                                            </motion.text>
                                        );
                                    })}
                                </svg>
                            </div>
                        </CardContent>
                    </Card>
                </motion.div>
            </motion.div>
        </AppLayout>
    );
}