// resources/js/Pages/Devices.tsx
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { motion } from 'framer-motion';
import { Cpu, Activity, Monitor, Camera, Fingerprint, Radio } from 'lucide-react';
import { useState, useMemo, useEffect } from 'react';

interface Device {
  id: number;
  terminal_name: string;
  device_type: string;
  status: 'online' | 'offline' | 'error';
  ip_address?: string;
  wifi_strength?: number;
  uptime?: string;
  free_memory?: number;
  last_seen: string;
}

interface Props {
  devices: Device[];
  lastUpdate: string;
  setupRequired?: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Devices',
    href: '/devices',
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
  y: -2,
  transition: { duration: 0.2 },
};

const iconHover = {
  scale: 1.1,
  rotate: 5,
  transition: { duration: 0.2 },
};

// Expected devices - these will show even if not in database yet
const expectedDevices = [
  { 
    type: 'esp32', 
    name: 'ESP32', 
    icon: Cpu,
    description: 'Main microcontroller',
    terminal_names: ['main_controller', 'esp32', 'controller']
  },
  { 
    type: 'lcd', 
    name: 'LCD Display', 
    icon: Monitor,
    description: 'User interface display',
    terminal_names: ['lcd', 'display']
  },
  { 
    type: 'esp32-cam', 
    name: 'ESP32-CAM', 
    icon: Camera,
    description: 'Security camera module',
    terminal_names: ['camera', 'esp32-cam', 'cam']
  },
  { 
    type: 'fingerprint', 
    name: 'Fingerprint Sensor', 
    icon: Fingerprint,
    description: 'Biometric authentication',
    terminal_names: ['fingerprint', 'finger', 'biometric']
  },
  { 
    type: 'rfid', 
    name: 'RFID Reader', 
    icon: Radio,
    description: 'Card and tag reader',
    terminal_names: ['rfid', 'reader', 'card_reader']
  }
];

export default function Devices({ devices: initialDevices, lastUpdate: initialLastUpdate, setupRequired = false }: Props) {
  const [devices, setDevices] = useState<Device[]>(initialDevices);
  const [needsSetup, setNeedsSetup] = useState(setupRequired);

  // Get device status from actual data with better matching
  const getDeviceStatus = (expectedDevice: typeof expectedDevices[0]) => {
    // Try to find device by device_type first
    let foundDevice = devices.find(d => 
      d.device_type === expectedDevice.type
    );
    
    // If not found, try to match by terminal_name
    if (!foundDevice) {
      foundDevice = devices.find(d => 
        expectedDevice.terminal_names.some(name => 
          d.terminal_name.toLowerCase().includes(name.toLowerCase())
        )
      );
    }
    
    // If still not found, try direct terminal_name match
    if (!foundDevice && expectedDevice.terminal_names.length > 0) {
      foundDevice = devices.find(d => 
        expectedDevice.terminal_names.includes(d.terminal_name.toLowerCase())
      );
    }
    
    return foundDevice || { status: 'offline' as const };
  };

  // Get status display configuration
  const getStatusConfig = (status: string) => {
    switch (status) {
      case 'online':
        return {
          color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
          iconColor: 'text-green-600',
          dotColor: 'bg-green-500',
          text: 'Online'
        };
      case 'offline':
        return {
          color: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
          iconColor: 'text-gray-400',
          dotColor: 'bg-gray-400',
          text: 'Offline'
        };
      case 'error':
        return {
          color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
          iconColor: 'text-red-600',
          dotColor: 'bg-red-500',
          text: 'Error'
        };
      default:
        return {
          color: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
          iconColor: 'text-gray-400',
          dotColor: 'bg-gray-400',
          text: 'Offline'
        };
    }
  };

  // Calculate system stats
  const systemStats = useMemo(() => {
    const onlineCount = devices.filter(d => d.status === 'online').length;
    const totalCount = expectedDevices.length;
    
    return {
      onlineCount,
      totalCount,
      healthPercentage: totalCount > 0 ? Math.round((onlineCount / totalCount) * 100) : 0
    };
  }, [devices]);

  // Debug: Log the devices data
  useEffect(() => {
    console.log('Devices data:', devices);
    console.log('Online count:', systemStats.onlineCount);
  }, [devices, systemStats.onlineCount]);

  if (needsSetup) {
    return (
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Device Setup Required" />
        <motion.div
          initial="hidden"
          animate="show"
          variants={container}
          className="flex h-full flex-1 flex-col gap-6 p-6"
        >
          <motion.div variants={item} className="flex flex-col items-center justify-center flex-1">
            <motion.div
              className="text-center max-w-md"
              initial={{ scale: 0.9, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              transition={{ type: 'spring', stiffness: 300 }}
            >
              <motion.div
                className="w-20 h-20 bg-yellow-100 dark:bg-yellow-900/30 rounded-full flex items-center justify-center mx-auto mb-6"
                whileHover={{ scale: 1.1, rotate: 5 }}
              >
                <Cpu className="h-10 w-10 text-yellow-600" />
              </motion.div>
              <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">Setup Required</h1>
              <p className="text-gray-600 dark:text-gray-400 mb-6">
                The devices database needs to be initialized before you can monitor your system.
              </p>
              <div className="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl p-4 mb-6">
                <p className="text-sm text-yellow-800 dark:text-yellow-300 font-medium mb-2">
                  Run this command in your terminal:
                </p>
                <code className="block bg-yellow-100 dark:bg-yellow-900/30 px-3 py-2 rounded-lg text-sm font-mono text-yellow-900 dark:text-yellow-200">
                  php artisan migrate
                </code>
              </div>
            </motion.div>
          </motion.div>
        </motion.div>
      </AppLayout>
    );
  }

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Device Status" />

      <motion.div
        initial="hidden"
        animate="show"
        variants={container}
        className="flex h-full flex-1 flex-col gap-6 p-6"
      >
        {/* Header Section */}
        <motion.div 
          variants={item}
          className="flex flex-col gap-4"
        >
          <motion.div
            initial={{ x: -20 }}
            animate={{ x: 0 }}
            transition={{ type: 'spring', stiffness: 300 }}
          >
            <h1 className="text-3xl font-bold text-gray-900 dark:text-white">Device Status</h1>
            <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
              Real-time monitoring of all Intelli-Lock components
            </p>
          </motion.div>
        </motion.div>

        {/* System Overview Cards */}
        <motion.div variants={item} className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* System Health Card */}
          <motion.div
            variants={item}
            whileHover={cardHover}
            className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm"
          >
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900 dark:text-white">System Health</h3>
              <motion.div whileHover={iconHover}>
                <Activity className="h-5 w-5 text-blue-600" />
              </motion.div>
            </div>
            <div className="flex items-end justify-between">
              <div>
                <p className="text-3xl font-bold text-gray-900 dark:text-white">
                  {systemStats.healthPercentage}%
                </p>
                <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                  {systemStats.onlineCount}/{systemStats.totalCount} devices online
                </p>
              </div>
              <div className={`w-3 h-3 rounded-full ${
                systemStats.healthPercentage === 100 ? 'bg-green-500' :
                systemStats.healthPercentage >= 50 ? 'bg-yellow-500' : 'bg-red-500'
              }`} />
            </div>
          </motion.div>

          {/* Active Devices Card */}
          <motion.div
            variants={item}
            whileHover={cardHover}
            className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm"
          >
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Active Devices</h3>
              <motion.div whileHover={iconHover}>
                <Activity className="h-5 w-5 text-green-600" />
              </motion.div>
            </div>
            <div>
              <p className="text-3xl font-bold text-gray-900 dark:text-white">
                {systemStats.onlineCount}
              </p>
              <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Currently connected
              </p>
            </div>
          </motion.div>
        </motion.div>

        {/* Devices Grid */}
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
                  <Cpu className="h-5 w-5 text-blue-600" />
                </motion.div>
                Intelli-Lock Components
                <span className="text-sm font-normal text-gray-500 dark:text-gray-400 ml-2">
                  ({expectedDevices.length} devices)
                </span>
              </motion.h2>
            </div>
            
            {/* Devices Grid */}
            <div className="flex-1 overflow-hidden">
              <div className="h-full overflow-auto custom-scrollbar p-6">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                  {expectedDevices.map((expectedDevice, index) => {
                    const deviceStatus = getDeviceStatus(expectedDevice);
                    const statusConfig = getStatusConfig(deviceStatus.status);
                    const IconComponent = expectedDevice.icon;
                    
                    return (
                      <motion.div
                        key={expectedDevice.type}
                        variants={item}
                        transition={{ delay: index * 0.1 }}
                        whileHover={cardHover}
                        className={`bg-white dark:bg-gray-800 rounded-xl border-2 p-6 shadow-sm transition-all duration-200 ${
                          deviceStatus.status === 'online' 
                            ? 'border-green-200 dark:border-green-800' 
                            : deviceStatus.status === 'error'
                            ? 'border-red-200 dark:border-red-800'
                            : 'border-gray-200 dark:border-gray-700'
                        }`}
                      >
                        <div className="flex items-start justify-between mb-4">
                          <div className="flex items-center gap-3">
                            <motion.div 
                              className={`p-3 rounded-xl ${statusConfig.iconColor} bg-opacity-10`}
                              whileHover={{ scale: 1.1 }}
                            >
                              <IconComponent className="h-6 w-6" />
                            </motion.div>
                            <div>
                              <h3 className="font-semibold text-gray-900 dark:text-white">
                                {expectedDevice.name}
                              </h3>
                              <p className="text-sm text-gray-600 dark:text-gray-400">
                                {expectedDevice.description}
                              </p>
                            </div>
                          </div>
                          <motion.span 
                            className={`inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium ${statusConfig.color}`}
                            whileHover={{ scale: 1.05 }}
                          >
                            <span className={`w-2 h-2 rounded-full ${statusConfig.dotColor}`} />
                            {statusConfig.text}
                          </motion.span>
                        </div>

                        {/* Device Details - Simplified (IP Address only) */}
                        <div className="space-y-3">
                          {deviceStatus.status === 'online' && deviceStatus.ip_address && (
                            <div className="flex items-center justify-between text-sm">
                              <span className="text-gray-500 dark:text-gray-400">IP Address</span>
                              <span className="font-mono text-gray-900 dark:text-white text-xs">
                                {deviceStatus.ip_address}
                              </span>
                            </div>
                          )}
                        </div>
                      </motion.div>
                    );
                  })}
                </div>

                {/* Empty State */}
                {devices.length === 0 && (
                  <motion.div 
                    className="flex flex-col items-center justify-center py-12"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 0.3 }}
                  >
                    <Cpu className="h-16 w-16 text-gray-300 dark:text-gray-600 mb-4" />
                    <div className="text-lg font-medium text-gray-900 dark:text-white mb-2">
                      No devices connected
                    </div>
                    <p className="text-sm text-gray-500 dark:text-gray-400 text-center max-w-md mb-4">
                      Devices will appear here when they connect to the system via MQTT.
                    </p>
                    <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 max-w-md">
                      <p className="text-sm text-blue-800 dark:text-blue-300 font-medium mb-2">
                        Make sure your MQTT listener is running:
                      </p>
                      <code className="block bg-blue-100 dark:bg-blue-900/30 px-3 py-2 rounded-lg text-sm font-mono text-blue-900 dark:text-blue-200">
                        php artisan mqtt:listen
                      </code>
                    </div>
                  </motion.div>
                )}
              </div>
            </div>
          </div>
        </motion.div>
      </motion.div>

      <style jsx>{`
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