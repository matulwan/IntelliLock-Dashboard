import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Video, Wifi, WifiOff, Search, Filter, Clock } from 'lucide-react';
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
            staggerChildren: 0.05,
        },
    },
};

const item = {
    hidden: { opacity: 0, y: 10 },
    show: { opacity: 1, y: 0, transition: { duration: 0.3 } },
};

const cardHover = {
    scale: 1.05,
    transition: { duration: 0.2 },
};

const imageHover = {
    scale: 1.1,
    transition: { duration: 0.3 },
};

interface Photo {
    id: number;
    url: string;
    time: string;
    time_ago: string;
    event_type: string;
    device: string;
    notes: string | null;
    user: string;
}

interface Stats {
    total: number;
    today: number;
    last_snap_time: string | null;
    last_snap_ago: string | null;
}

interface Device {
    status: string;
    last_seen: string | null;
}

interface Filters {
    device: string;
    filter: string;
    search: string;
}

interface Props {
    photos: Photo[];
    stats: Stats;
    device: Device;
    filters: Filters;
}

export default function SecuritySnaps({ photos, stats, device, filters: initialFilters }: Props) {
    const [searchTerm, setSearchTerm] = useState(initialFilters.search || '');
    const [selectedTime, setSelectedTime] = useState(initialFilters.filter || 'all');
    
    // Apply filters with debounce
    const handleSearch = (value: string) => {
        setSearchTerm(value);
        router.get(route('security-snaps'), {
            ...initialFilters,
            search: value,
            filter: selectedTime,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleFilterChange = (value: string) => {
        setSelectedTime(value);
        router.get(route('security-snaps'), {
            ...initialFilters,
            search: searchTerm,
            filter: value,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Filter snaps client-side for instant feedback (optional)
    const filteredSnaps = photos.filter(photo => {
        const matchesSearch = !searchTerm || 
            photo.time.toLowerCase().includes(searchTerm.toLowerCase()) ||
            photo.user.toLowerCase().includes(searchTerm.toLowerCase()) ||
            photo.event_type.toLowerCase().includes(searchTerm.toLowerCase());
        return matchesSearch;
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Security Snaps" />
            <motion.div
                initial="hidden"
                animate="show"
                variants={container}
                className="flex flex-col h-full gap-3 p-3 md:p-4"
            >
                {/* Quick Stats Row - Compact */}
                <motion.div variants={item} className="grid grid-cols-3 gap-2 mb-1">
                    <motion.div whileHover={cardHover}>
                        <Card className="p-2 shadow-none border">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-1">
                                    <Video className="h-3 w-3 text-blue-600" />
                                    <span className="text-xs text-muted-foreground">Total</span>
                                </div>
                                <span className="text-sm font-bold text-blue-700">
                                    {stats.total}
                                </span>
                            </div>
                        </Card>
                    </motion.div>

                    <motion.div whileHover={cardHover}>
                        <Card className="p-2 shadow-none border">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-1">
                                    <Wifi className={`h-3 w-3 ${device.status === 'online' ? 'text-green-600' : 'text-gray-400'}`} />
                                    <span className="text-xs text-muted-foreground">Status</span>
                                </div>
                                <span className="text-xs font-medium text-muted-foreground">
                                    {device.status === 'online' ? 'Online' : 'Online'}
                                </span>
                            </div>
                        </Card>
                    </motion.div>

                    <motion.div whileHover={cardHover}>
                        <Card className="p-2 shadow-none border">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-1">
                                    <Clock className="h-3 w-3 text-orange-600" />
                                    <span className="text-xs text-muted-foreground">Last</span>
                                </div>
                                <span className="text-xs font-medium text-muted-foreground text-right truncate">
                                    {stats.last_snap_ago || '-'}
                                </span>
                            </div>
                        </Card>
                    </motion.div>
                </motion.div>

                {/* Controls - Compact */}
                <motion.div variants={item} className="flex flex-col sm:flex-row gap-2">
                    <motion.div 
                        whileHover={{ scale: 1.01 }} 
                        className="relative flex-1"
                    >
                        <Search className="absolute left-2 top-1/2 -translate-y-1/2 h-3 w-3 text-muted-foreground" />
                        <input
                            type="text"
                            placeholder="Search snaps..."
                            className="w-full pl-7 pr-3 py-1.5 border rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 text-xs"
                            value={searchTerm}
                            onChange={(e) => handleSearch(e.target.value)}
                        />
                    </motion.div>

                    <motion.div
                        whileHover={{ scale: 1.01 }}
                        className="relative flex items-center min-w-[120px]"
                    >
                        <Filter className="absolute left-2 h-3 w-3 text-muted-foreground" />
                        <select
                            className="w-full pl-7 pr-6 py-1.5 border rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 text-xs appearance-none bg-white"
                            value={selectedTime}
                            onChange={(e) => handleFilterChange(e.target.value)}
                        >
                            <option value="all">All Time</option>
                            <option value="recent">Last 7 Days</option>
                            <option value="today">Today</option>
                        </select>
                    </motion.div>
                </motion.div>

                {/* Snaps Grid - Exactly 6 per row */}
                <motion.div variants={item} className="flex-1">
                    <Card className="flex flex-col h-full">
                        <CardHeader className="pb-2 px-4 pt-3">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-base">Security Snaps</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="flex-1 p-3">
                            {filteredSnaps.length > 0 ? (
                                <motion.div 
                                    className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3"
                                    layout
                                >
                                    {filteredSnaps.map((photo) => (
                                        <motion.div
                                            key={photo.id}
                                            variants={item}
                                            initial={{ opacity: 0, scale: 0.9 }}
                                            animate={{ opacity: 1, scale: 1 }}
                                            transition={{ duration: 0.2 }}
                                            whileHover={cardHover}
                                            className="relative rounded-lg overflow-hidden border bg-white shadow-xs flex flex-col group cursor-pointer"
                                        >
                                            {/* Clean image container with time on bottom left */}
                                            <div className="relative w-full pt-[125%]"> {/* 4:5 aspect ratio */}
                                                <motion.img 
                                                    src={photo.url.replace(/\\\//g, '/')} 
                                                    alt={`Security Snap ${photo.id}`}
                                                    className="absolute inset-0 w-full h-full object-cover"
                                                    style={{ 
                                                        transform: 'rotate(90deg)',
                                                        transformOrigin: 'center'
                                                    }}
                                                    whileHover={imageHover}
                                                    onError={(e) => {
                                                        console.error('Failed to load image:', photo.url);
                                                        (e.target as HTMLImageElement).src = 'https://via.placeholder.com/200x250?text=Image+Error';
                                                    }}
                                                />
                                                
                                                {/* Time display - bottom left, always visible */}
                                                <div className="absolute bottom-2 left-2 bg-black/60 text-white text-[10px] px-2 py-1 rounded-md backdrop-blur-sm">
                                                    {photo.time_ago}
                                                </div>
                                            </div>

                                            {/* Additional info on hover */}
                                            <motion.div 
                                                className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex flex-col justify-center items-center p-3"
                                            >
                                                
                                            </motion.div>
                                        </motion.div>
                                    ))}
                                </motion.div>
                            ) : (
                                <motion.div 
                                    initial={{ opacity: 0 }}
                                    animate={{ opacity: 1 }}
                                    className="flex flex-col items-center justify-center py-8"
                                >
                                    <Video className="h-12 w-12 text-gray-300 mb-3" />
                                    <p className="text-gray-400 text-sm mb-1">No security snaps found</p>
                                    <p className="text-gray-300 text-xs text-center">
                                        Adjust your search or filter criteria
                                    </p>
                                </motion.div>
                            )}
                        </CardContent>
                    </Card>
                </motion.div>
            </motion.div>
        </AppLayout>
    );
}