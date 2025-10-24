import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Video, Wifi, WifiOff } from 'lucide-react';
import { motion } from 'framer-motion';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Security Snaps',
        href: '/live-camera',
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

const imageHover = {
    scale: 1.05,
    transition: { duration: 0.3 },
};

// Mock for security snaps
const securitySnaps = [
    { url: 'https://placekitten.com/320/240', time: '2024-01-16 09:40' },
    { url: 'https://placekitten.com/321/240', time: '2024-01-16 08:55' },
    { url: 'https://placekitten.com/322/240', time: '2024-01-16 08:10' },
    { url: 'https://placekitten.com/323/240', time: '2024-01-16 07:30' },
    { url: 'https://placekitten.com/324/240', time: '2024-01-16 06:50' },
    { url: 'https://placekitten.com/325/240', time: '2024-01-16 06:15' },
    { url: 'https://placekitten.com/326/240', time: '2024-01-16 05:40' },
    { url: 'https://placekitten.com/327/240', time: '2024-01-16 05:10' },
];

export default function SecuritySnaps() {
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedTime, setSelectedTime] = useState('all');
    
    // Quick stats
    const totalSnaps = securitySnaps.length;
    const lastSnapTime = securitySnaps[0]?.time || '-';

    // Filter snaps based on search and time selection
    const filteredSnaps = securitySnaps.filter(snap => {
        const matchesSearch = snap.time.toLowerCase().includes(searchTerm.toLowerCase());
        const matchesTime = selectedTime === 'all' || 
                          (selectedTime === 'recent' && snap.time === securitySnaps[0].time) ||
                          (selectedTime === 'today' && snap.time.includes('2024-01-16'));
        return matchesSearch && matchesTime;
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Security Snaps" />
            <motion.div
                initial="hidden"
                animate="show"
                variants={container}
                className="flex flex-col h-full gap-4 p-6"
            >
                {/* Quick Stats Row */}
                <motion.div variants={item} className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
                    <motion.div whileHover={cardHover}>
                        <Card className="col-span-1 flex flex-row items-center gap-4 p-4 shadow-none border">
                            <div className="flex items-center gap-2">
                                <Video className="h-5 w-5 text-blue-600" />
                                <span className="text-xs font-medium text-muted-foreground">Total Snaps</span>
                            </div>
                            <motion.span 
                                className="ml-auto text-lg font-bold text-blue-700"
                                initial={{ scale: 0.8 }}
                                animate={{ scale: 1 }}
                                transition={{ type: 'spring', stiffness: 500 }}
                            >
                                {totalSnaps}
                            </motion.span>
                        </Card>
                    </motion.div>

                    <motion.div whileHover={cardHover}>
                        <Card className="col-span-1 flex flex-row items-center gap-4 p-4 shadow-none border">
                            <div className="flex items-center gap-2">
                                <Wifi className="h-5 w-5 text-green-600" />
                                <span className="text-xs font-medium text-muted-foreground">Last Active</span>
                            </div>
                            <span className="ml-auto text-xs font-medium text-muted-foreground">{lastSnapTime}</span>
                        </Card>
                    </motion.div>

                    <motion.div whileHover={cardHover}>
                        <Card className="col-span-1 flex flex-row items-center gap-4 p-4 shadow-none border">
                            <div className="flex items-center gap-2">
                                <WifiOff className="h-5 w-5 text-red-600" />
                                <span className="text-xs font-medium text-muted-foreground">Offline Since</span>
                            </div>
                            <span className="ml-auto text-xs font-medium text-muted-foreground">-</span>
                        </Card>
                    </motion.div>
                </motion.div>

                {/* Controls */}
                <motion.div variants={item} className="flex gap-4">
                    <motion.div 
                        whileHover={{ scale: 1.01 }} 
                        className="relative flex-1 max-w-md"
                    >
                        <input
                            type="text"
                            placeholder="Search by timestamp..."
                            className="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                        <Video className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    </motion.div>

                    <motion.select
                        whileHover={{ scale: 1.01 }}
                        className="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        value={selectedTime}
                        onChange={(e) => setSelectedTime(e.target.value)}
                    >
                        <option value="all">All Time</option>
                        <option value="recent">Most Recent</option>
                        <option value="today">Today</option>
                    </motion.select>
                </motion.div>

                {/* Snaps Grid */}
                <motion.div variants={item}>
                    <Card className="flex-1 flex flex-col">
                        <CardContent>
                            {filteredSnaps.length > 0 ? (
                                <motion.div 
                                    className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4"
                                    layout
                                >
                                    {filteredSnaps.map((snap, idx) => (
                                        <motion.div
                                            key={idx}
                                            variants={item}
                                            initial={{ opacity: 0, scale: 0.9 }}
                                            animate={{ opacity: 1, scale: 1 }}
                                            transition={{ duration: 0.3 }}
                                            whileHover={{ scale: 1.03 }}
                                            className="relative rounded-xl overflow-hidden border bg-white shadow-sm flex flex-col group"
                                        >
                                            <motion.div whileHover={imageHover}>
                                                <img 
                                                    src={snap.url} 
                                                    alt={`Security Snap ${idx + 1}`} 
                                                    className="w-full h-40 object-cover cursor-pointer" 
                                                />
                                            </motion.div>
                                            <motion.span 
                                                className="absolute bottom-2 right-2 bg-black/60 text-white text-xs px-2 py-0.5 rounded"
                                                initial={{ opacity: 0.7 }}
                                                whileHover={{ opacity: 1 }}
                                            >
                                                {snap.time}
                                            </motion.span>
                                            <motion.div 
                                                className="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center"
                                            >
                                                <motion.button 
                                                    whileHover={{ scale: 1.1 }}
                                                    className="bg-white/90 text-black px-3 py-1 rounded-full text-xs font-medium shadow"
                                                >
                                                    View Details
                                                </motion.button>
                                            </motion.div>
                                        </motion.div>
                                    ))}
                                </motion.div>
                            ) : (
                                <motion.div 
                                    initial={{ opacity: 0 }}
                                    animate={{ opacity: 1 }}
                                    className="flex flex-col items-center justify-center py-12"
                                >
                                    <Video className="h-12 w-12 text-gray-400 mb-4" />
                                    <p className="text-gray-500">No security snaps found</p>
                                </motion.div>
                            )}
                        </CardContent>
                    </Card>
                </motion.div>
            </motion.div>
        </AppLayout>
    );
}