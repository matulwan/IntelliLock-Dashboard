import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Search, Plus, Edit, Trash2, Shield, User, Users } from 'lucide-react';
import { motion } from 'framer-motion';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'User Management',
        href: '/user-management',
    },
];

// Animation variants
const containerVariants = {
    hidden: { opacity: 0 },
    show: {
        opacity: 1,
        transition: {
            staggerChildren: 0.1,
        },
    },
};

const itemVariants = {
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

export default function UserManagement() {
    const { users, roles } = usePage<PageProps>().props;
    const filteredRoles = roles.filter(role => role.name !== 'Staff');
    const totalUsers = filteredRoles.reduce((acc, role) => acc + role.count, 0);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedRole, setSelectedRole] = useState('all');

    const filteredUsers = users
        .filter(user => user.role !== 'Staff')
        .filter(user => {
            const matchesSearch = user.name.toLowerCase().includes(searchTerm.toLowerCase());
            const matchesRole = selectedRole === 'all' || user.role.toLowerCase() === selectedRole;
            return matchesSearch && matchesRole;
        });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Management" />
            <motion.div 
                initial="hidden"
                animate="show"
                variants={containerVariants}
                className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-6"
            >
                {/* Quick Stats Row */}
                <motion.div 
                    variants={containerVariants}
                    className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2"
                >
                    <motion.div variants={itemVariants} whileHover={cardHover}>
                        <Card className="col-span-1 flex flex-row items-center gap-4 p-4 shadow-none border">
                            <div className="flex items-center gap-2">
                                <Shield className="h-5 w-5 text-blue-600" />
                                <span className="text-xs font-medium text-muted-foreground">Lecturers</span>
                            </div>
                            <div className="ml-auto flex flex-col items-end">
                                <span className="text-lg font-bold text-blue-700">{filteredRoles[0]?.count ?? 0}</span>
                                <span className="text-xs text-muted-foreground">{filteredRoles[0] ? Math.round((filteredRoles[0].count / totalUsers) * 100) : 0}%</span>
                            </div>
                        </Card>
                    </motion.div>
                    
                    <motion.div variants={itemVariants} whileHover={cardHover}>
                        <Card className="col-span-1 flex flex-row items-center gap-4 p-4 shadow-none border">
                            <div className="flex items-center gap-2">
                                <Shield className="h-5 w-5 text-green-600" />
                                <span className="text-xs font-medium text-muted-foreground">Students</span>
                            </div>
                            <div className="ml-auto flex flex-col items-end">
                                <span className="text-lg font-bold text-green-700">{filteredRoles[1]?.count ?? 0}</span>
                                <span className="text-xs text-muted-foreground">{filteredRoles[1] ? Math.round((filteredRoles[1].count / totalUsers) * 100) : 0}%</span>
                            </div>
                        </Card>
                    </motion.div>
                    
                    <motion.div variants={itemVariants} whileHover={cardHover}>
                        <Card className="col-span-1 flex flex-row items-center gap-4 p-4 shadow-none border">
                            <div className="flex items-center gap-2">
                                <Users className="h-5 w-5 text-gray-500" />
                                <span className="text-xs font-medium text-muted-foreground">Total</span>
                            </div>
                            <span className="ml-auto text-lg font-bold text-gray-700">{totalUsers}</span>
                        </Card>
                    </motion.div>
                </motion.div>

                {/* Users Table */}
                <motion.div variants={itemVariants}>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                            <div className="flex items-center gap-2">
                                <User className="h-5 w-5 text-blue-600" />
                                <span className="text-xs font-semibold text-muted-foreground">Users</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <motion.div whileHover={{ scale: 1.01 }} className="relative">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                    <Input 
                                        placeholder="Search users..." 
                                        className="pl-10 w-48" 
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                    />
                                </motion.div>
                                <Select value={selectedRole} onValueChange={setSelectedRole}>
                                    <SelectTrigger className="w-32">
                                        <SelectValue placeholder="Role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All</SelectItem>
                                        <SelectItem value="lecturer">Lecturer</SelectItem>
                                        <SelectItem value="student">Student</SelectItem>
                                    </SelectContent>
                                </Select>
                                <motion.div whileHover={buttonHover}>
                                    <Link href={route('user-management.add')}>
                                        <Button className="bg-blue-600 hover:bg-blue-700 text-white">
                                            <Plus className="h-4 w-4 mr-2" />
                                            Add User
                                        </Button>
                                    </Link>
                                </motion.div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>User</TableHead>
                                            <TableHead>Role</TableHead>
                                            <TableHead>Phone Number</TableHead> {/* Added */}
                                            <TableHead>Last Login</TableHead>
                                            <TableHead>Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {filteredUsers.map((user, index) => (
                                            <motion.tr
                                                key={user.id}
                                                initial={{ opacity: 0, y: 10 }}
                                                animate={{ opacity: 1, y: 0 }}
                                                transition={{ delay: index * 0.05, duration: 0.3 }}
                                                whileHover={rowHover}
                                                className="transition-colors hover:bg-muted/50 border-b-0"
                                            >
                                                <TableCell>
                                                    <div className="flex items-center gap-3">
                                                        <motion.div whileHover={{ scale: 1.1 }}>
                                                            <Avatar className="h-8 w-8">
                                                                <AvatarImage src={user.avatar || undefined} />
                                                                <AvatarFallback>
                                                                    {user.name.split(' ').map(n => n[0]).join('')}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                        </motion.div>
                                                        <div className="font-medium text-sm">{user.name}</div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <motion.div whileHover={{ scale: 1.05 }}>
                                                        <Badge
                                                            variant={
                                                                user.role === 'Lecturer' ? 'default' :
                                                                'secondary'
                                                            }
                                                            className={
                                                                user.role === 'Lecturer' ? 'bg-green-100 text-green-700 border-green-200' :
                                                                'bg-blue-100 text-blue-700 border-blue-200'
                                                            + ' text-xs px-2 py-0.5'}
                                                        >
                                                            {user.role}
                                                        </Badge>
                                                    </motion.div>
                                                </TableCell>
                                                <TableCell className="text-xs text-muted-foreground">
                                                    {user.phone ?? '-'}
                                                </TableCell>
                                                <TableCell className="text-xs text-muted-foreground">{user.lastLogin}</TableCell>
                                                <TableCell>
                                                    <div className="flex gap-2">
                                                        <motion.div whileHover={{ scale: 1.1 }}>
                                                            <Button variant="ghost" size="sm" aria-label={`Edit ${user.name}`}>
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        </motion.div>
                                                        <motion.div whileHover={{ scale: 1.1 }}>
                                                            <Button variant="ghost" size="sm" className="text-red-600 hover:text-red-700" aria-label={`Delete ${user.name}`}>
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        </motion.div>
                                                    </div>
                                                </TableCell>
                                            </motion.tr>
                                        ))}
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

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    phone?: string; // Added phone field
    lastLogin: string | null;
    avatar: string | null;
}

interface Role {
    name: string;
    count: number;
}

interface PageProps {
    users: User[];
    roles: Role[];
    [key: string]: any;
}