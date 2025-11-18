import React, { useState, FormEventHandler } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, BadgeCheck, XCircle, RefreshCw, Fingerprint, CreditCard } from 'lucide-react';
import { motion } from 'framer-motion';
import mqtt from 'mqtt';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';

const roles = [
    { value: 'Student', label: 'Student' },
    { value: 'Lecturer', label: 'Lecturer' },
    { value: 'Key', label: 'Register Room' },
];

type AddUserForm = {
    name: string;
    phone: string;
    matrix_number: string;
    role: string;
    rfid_uid: string;
    fingerprint_id: string;
};

type Status = 'waiting' | 'success' | 'failed';

export default function AddUser() {
    const { data, setData, post, processing, errors, reset } = useForm<AddUserForm>({
        name: '',
        phone: '',
        matrix_number: '',
        role: '',
        rfid_uid: '',
        fingerprint_id: '',
    });

    const [rfidStatus, setRfidStatus] = useState<Status>('waiting');
    const [biometricStatus, setBiometricStatus] = useState<Status>('waiting');
    const [rfidUid, setRfidUid] = useState<string | null>(null);
    const [biometricId, setBiometricId] = useState<string | null>(null);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        // prefer manually entered field, fallback to scanned
        setData({
            ...data,
            rfid_uid: data.rfid_uid || rfidUid || '',
            fingerprint_id: data.fingerprint_id || biometricId || '',
        });
        post(route('user-management.store'), {
            onFinish: () => reset(),
        });
    };

    const retryRfid = () => {
        setRfidStatus('waiting');
        setRfidUid(null);
    };
    const retryBiometric = () => {
        setBiometricStatus('waiting');
        setBiometricId(null);
    };

    React.useEffect(() => {
        const client = mqtt.connect('ws://192.168.0.50:9001');


        client.on('connect', () => {
            client.subscribe('rfid/scan');
            client.subscribe('biometric/scan');
        });

        client.on('message', (topic, message) => {
            if (topic === 'rfid/scan') {
                const uid = message.toString();
                if (uid) {
                    setRfidStatus('success');
                    setRfidUid(uid);
                    setData('rfid_uid', uid);
                } else {
                    setRfidStatus('failed');
                }
            }
            if (topic === 'biometric/scan') {
                const bioId = message.toString();
                if (bioId) {
                    setBiometricStatus('success');
                    setBiometricId(bioId);
                    setData('fingerprint_id', bioId);
                } else {
                    setBiometricStatus('failed');
                }
            }
        });

        return () => {
            client.end();
        };
    }, []);

    return (
        <AppLayout breadcrumbs={[{ title: 'User Management', href: '/user-management' }, { title: 'Add User', href: '/user-management/add' }]}> 
            <Head title="Add User" />
            <motion.div 
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.5 }}
                className="min-h-screen bg-gradient-to-br from-blue-50 to-purple-50 p-4 md:p-8"
            >
                <div className="max-w-7xl mx-auto">
                    <motion.div 
                        initial={{ y: 20, opacity: 0 }}
                        animate={{ y: 0, opacity: 1 }}
                        transition={{ delay: 0.2, duration: 0.5 }}
                        className="bg-white rounded-2xl shadow-xl overflow-hidden"
                    >
                        <div className="grid grid-cols-1 lg:grid-cols-2">
                            {/* Form Section */}
                            <div className="p-6 md:p-8 lg:p-10">
                                <div className="mb-8">
                                    <h1 className="text-2xl md:text-3xl font-bold text-gray-900">Add New User</h1>
                                    <p className="text-sm text-gray-500 mt-2">
                                        Register a new user with their credentials and hardware authentication
                                    </p>
                                </div>

                                <form className="space-y-6" onSubmit={submit}>
                                    <div className="grid grid-cols-1 gap-6">
                                        <div>
                                            <Label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                                                {data.role === 'Key' ? 'Key/Room Name' : 'Full Name'}
                                            </Label>
                                            <Input
                                                id="name"
                                                type="text"
                                                required
                                                autoFocus
                                                autoComplete="name"
                                                placeholder={data.role === 'Key' ? 'e.g. Lab A, Room 101' : ''}
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                disabled={processing}
                                                className="w-full"
                                            />
                                            <InputError message={errors.name} className="mt-1" />
                                        </div>

                                        {data.role !== 'Key' && (
                                            <div>
                                                <Label htmlFor="phone" className="block text-sm font-medium text-gray-700 mb-1">
                                                    Phone Number
                                                </Label>
                                                <Input
                                                    id="phone"
                                                    type="tel"
                                                    required
                                                    autoComplete="tel"
                                                    value={data.phone}
                                                    onChange={(e) => setData('phone', e.target.value)}
                                                    disabled={processing}
                                                    className="w-full"
                                                />
                                                <InputError message={errors.phone} className="mt-1" />
                                            </div>
                                        )}

                                        <div>
                                            <Label htmlFor="role" className="block text-sm font-medium text-gray-700 mb-1">
                                                Role
                                            </Label>
                                            <Select value={data.role} onValueChange={value => setData('role', value)}>
                                                <SelectTrigger id="role" className="w-full">
                                                    <SelectValue placeholder="Select role" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {roles.map((role) => (
                                                        <SelectItem key={role.value} value={role.value}>{role.label}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.role} />
                                        </div>

                                        {data.role === 'Key' && (
                                            <motion.div
                                                initial={{ opacity: 0, height: 0 }}
                                                animate={{ opacity: 1, height: 'auto' }}
                                                exit={{ opacity: 0, height: 0 }}
                                                transition={{ duration: 0.3 }}
                                            >
                                                <Label htmlFor="matrix_number" className="block text-sm font-medium text-gray-700 mb-1">
                                                    Description (Optional)
                                                </Label>
                                                <Input
                                                    id="matrix_number"
                                                    type="text"
                                                    placeholder="e.g. Lab A, Room 101"
                                                    autoComplete="off"
                                                    value={data.matrix_number}
                                                    onChange={(e) => setData('matrix_number', e.target.value)}
                                                    disabled={processing}
                                                    className="w-full"
                                                />
                                                <InputError message={errors.matrix_number} />
                                            </motion.div>
                                        )}

                                        {data.role === 'Student' && (
                                            <motion.div
                                                initial={{ opacity: 0, height: 0 }}
                                                animate={{ opacity: 1, height: 'auto' }}
                                                exit={{ opacity: 0, height: 0 }}
                                                transition={{ duration: 0.3 }}
                                            >
                                                <Label htmlFor="matrix_number" className="block text-sm font-medium text-gray-700 mb-1">
                                                    Matrix Number
                                                </Label>
                                                <Input
                                                    id="matrix_number"
                                                    type="text"
                                                    required
                                                    autoComplete="off"
                                                    value={data.matrix_number}
                                                    onChange={(e) => setData('matrix_number', e.target.value)}
                                                    disabled={processing}
                                                    className="w-full"
                                                />
                                                <InputError message={errors.matrix_number} />
                                            </motion.div>
                                        )}
                                    </div>

                                    <div className="space-y-6">
                                        {/* Manual UID Inputs */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <Label htmlFor="rfid_uid" className="block text-sm font-medium text-gray-700 mb-1">
                                                    RFID UID {data.role === 'Key' && <span className="text-red-500">*</span>} (paste or scan)
                                                </Label>
                                                <Input
                                                    id="rfid_uid"
                                                    type="text"
                                                    placeholder="e.g. 0A3B9C..."
                                                    value={data.rfid_uid}
                                                    onChange={(e) => setData('rfid_uid', e.target.value)}
                                                    disabled={processing}
                                                    className="w-full"
                                                />
                                                <InputError message={errors.rfid_uid as unknown as string} className="mt-1" />
                                            </div>
                                            {data.role !== 'Key' && (
                                                <div>
                                                    <Label htmlFor="fingerprint_id" className="block text-sm font-medium text-gray-700 mb-1">
                                                        Fingerprint ID (number)
                                                    </Label>
                                                    <Input
                                                        id="fingerprint_id"
                                                        type="number"
                                                        placeholder="e.g. 12"
                                                        value={data.fingerprint_id}
                                                        onChange={(e) => setData('fingerprint_id', e.target.value)}
                                                        disabled={processing}
                                                        className="w-full"
                                                    />
                                                    <InputError message={errors.fingerprint_id as unknown as string} className="mt-1" />
                                                </div>
                                            )}
                                        </div>

                                        
                                    </div>

                                    <div className="flex flex-col sm:flex-row gap-3 pt-2">
                                        <Button 
                                            type="submit" 
                                            className="w-full sm:w-auto bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 shadow-md"
                                            disabled={processing}
                                        >
                                            {processing && <LoaderCircle className="h-4 w-4 animate-spin mr-2" />}
                                            Register User
                                        </Button>
                                        <Button 
                                            variant="outline" 
                                            asChild
                                            className="w-full sm:w-auto"
                                        >
                                            <TextLink href={route('user-management')}>
                                                Cancel
                                            </TextLink>
                                        </Button>
                                    </div>
                                </form>
                            </div>

                            {/* Visual Section */}
                            <div className="hidden lg:flex bg-gradient-to-br from-blue-600 to-purple-700 p-10 flex-col justify-center relative overflow-hidden">
                                <div className="absolute inset-0 opacity-10">
                                    <div className="absolute top-0 left-20 w-32 h-32 bg-white rounded-full filter blur-xl"></div>
                                    <div className="absolute bottom-20 right-20 w-64 h-64 bg-white rounded-full filter blur-xl"></div>
                                </div>
                                
                                <div className="relative z-10 text-white">
                                    <h2 className="text-3xl font-bold mb-4 leading-tight">
                                        Secure Access <br />Simplified
                                    </h2>
                                    <p className="text-blue-100 mb-8 max-w-md">
                                        Intelli-Lock provides seamless and secure access control with RFID and biometric authentication.
                                    </p>
                                    
                                    <div className="space-y-6">
                                        <div className="flex items-start gap-4">
                                            <div className="bg-white/20 p-2 rounded-lg">
                                                <Fingerprint className="h-5 w-5" />
                                            </div>
                                            <div>
                                                <h3 className="font-semibold">Biometric Authentication</h3>
                                                <p className="text-sm text-blue-100 mt-1">
                                                    Fast and secure fingerprint recognition for instant access.
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div className="flex items-start gap-4">
                                            <div className="bg-white/20 p-2 rounded-lg">
                                                <CreditCard className="h-5 w-5" />
                                            </div>
                                            <div>
                                                <h3 className="font-semibold">RFID Technology</h3>
                                                <p className="text-sm text-blue-100 mt-1">
                                                    Contactless entry with RFID cards for convenience.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </motion.div>
                </div>
            </motion.div>
        </AppLayout>
    );
}

