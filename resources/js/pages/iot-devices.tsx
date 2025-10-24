import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { 
  Wifi, 
  WifiOff, 
  AlertTriangle, 
  Monitor, 
  Unlock,
  Lock,
  RefreshCw,
  Signal,
  Clock,
  HardDrive
} from 'lucide-react';

interface IoTDevice {
  id: number;
  terminal_name: string;
  device_type: string;
  status: 'online' | 'offline' | 'error';
  ip_address?: string;
  wifi_strength?: number;
  uptime?: number;
  free_memory?: number;
  last_seen?: string;
  location?: string;
  description?: string;
  formatted_uptime?: string;
  wifi_strength_description?: string;
}

interface Stats {
  total_devices: number;
  online_devices: number;
  offline_devices: number;
}

interface Props {
  devices: IoTDevice[];
  stats: Stats;
}

const IoTDevicesPage: React.FC<Props> = ({ devices, stats }) => {
  const [loading, setLoading] = useState<string | null>(null);

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'online':
        return <Wifi className="h-4 w-4 text-green-500" />;
      case 'offline':
        return <WifiOff className="h-4 w-4 text-gray-500" />;
      case 'error':
        return <AlertTriangle className="h-4 w-4 text-red-500" />;
      default:
        return <Monitor className="h-4 w-4 text-gray-500" />;
    }
  };

  const getStatusBadge = (status: string) => {
    const variants = {
      online: 'bg-green-100 text-green-800',
      offline: 'bg-gray-100 text-gray-800',
      error: 'bg-red-100 text-red-800'
    };
    
    return (
      <Badge className={variants[status as keyof typeof variants] || variants.offline}>
        {status.charAt(0).toUpperCase() + status.slice(1)}
      </Badge>
    );
  };

  const getWifiStrengthIcon = (strength?: number) => {
    if (!strength) return <Signal className="h-4 w-4 text-gray-400" />;
    
    if (strength >= -50) return <Signal className="h-4 w-4 text-green-500" />;
    if (strength >= -60) return <Signal className="h-4 w-4 text-yellow-500" />;
    if (strength >= -70) return <Signal className="h-4 w-4 text-orange-500" />;
    return <Signal className="h-4 w-4 text-red-500" />;
  };

  const handleDoorControl = async (deviceId: number, action: string) => {
    setLoading(`${deviceId}-${action}`);
    
    try {
      await router.post(`/iot-devices/${deviceId}/control`, {
        action,
        duration: 5
      });
    } catch (error) {
      console.error('Door control failed:', error);
    } finally {
      setLoading(null);
    }
  };

  const formatBytes = (bytes?: number) => {
    if (!bytes) return 'Unknown';
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
  };

  return (
    <AppLayout>
      <Head title="IoT Devices" />
      
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">IoT Devices</h1>
            <p className="text-muted-foreground">
              Monitor and control your ESP32 access control devices
            </p>
          </div>
          <Button 
            onClick={() => router.reload()}
            variant="outline"
            className="flex items-center gap-2"
          >
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {/* Stats Cards */}
        <div className="grid gap-4 md:grid-cols-3">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Devices</CardTitle>
              <Monitor className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.total_devices}</div>
            </CardContent>
          </Card>
          
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Online</CardTitle>
              <Wifi className="h-4 w-4 text-green-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-green-600">{stats.online_devices}</div>
            </CardContent>
          </Card>
          
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Offline</CardTitle>
              <WifiOff className="h-4 w-4 text-gray-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-600">{stats.offline_devices}</div>
            </CardContent>
          </Card>
        </div>

        {/* Devices Grid */}
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {devices.map((device) => (
            <Card key={device.id} className="relative">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                  <CardTitle className="text-lg font-semibold">
                    {device.terminal_name}
                  </CardTitle>
                  {getStatusIcon(device.status)}
                </div>
                <div className="flex items-center gap-2">
                  {getStatusBadge(device.status)}
                  {device.location && (
                    <Badge variant="outline" className="text-xs">
                      {device.location}
                    </Badge>
                  )}
                </div>
              </CardHeader>
              
              <CardContent className="space-y-4">
                {/* Device Info */}
                <div className="space-y-2 text-sm">
                  {device.ip_address && (
                    <div className="flex items-center justify-between">
                      <span className="text-muted-foreground">IP Address:</span>
                      <span className="font-mono">{device.ip_address}</span>
                    </div>
                  )}
                  
                  {device.wifi_strength && (
                    <div className="flex items-center justify-between">
                      <span className="text-muted-foreground">WiFi Signal:</span>
                      <div className="flex items-center gap-1">
                        {getWifiStrengthIcon(device.wifi_strength)}
                        <span>{device.wifi_strength_description}</span>
                      </div>
                    </div>
                  )}
                  
                  {device.formatted_uptime && (
                    <div className="flex items-center justify-between">
                      <span className="text-muted-foreground">Uptime:</span>
                      <div className="flex items-center gap-1">
                        <Clock className="h-3 w-3" />
                        <span>{device.formatted_uptime}</span>
                      </div>
                    </div>
                  )}
                  
                  {device.free_memory && (
                    <div className="flex items-center justify-between">
                      <span className="text-muted-foreground">Free Memory:</span>
                      <div className="flex items-center gap-1">
                        <HardDrive className="h-3 w-3" />
                        <span>{formatBytes(device.free_memory)}</span>
                      </div>
                    </div>
                  )}
                  
                  {device.last_seen && (
                    <div className="flex items-center justify-between">
                      <span className="text-muted-foreground">Last Seen:</span>
                      <span>{new Date(device.last_seen).toLocaleString()}</span>
                    </div>
                  )}
                </div>

                {/* Control Buttons */}
                {device.status === 'online' && (
                  <div className="flex gap-2 pt-2">
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => handleDoorControl(device.id, 'open')}
                      disabled={loading === `${device.id}-open`}
                      className="flex-1"
                    >
                      {loading === `${device.id}-open` ? (
                        <RefreshCw className="h-3 w-3 animate-spin" />
                      ) : (
                        <Unlock className="h-3 w-3" />
                      )}
                      Open Door
                    </Button>
                    
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => handleDoorControl(device.id, 'lock')}
                      disabled={loading === `${device.id}-lock`}
                      className="flex-1"
                    >
                      {loading === `${device.id}-lock` ? (
                        <RefreshCw className="h-3 w-3 animate-spin" />
                      ) : (
                        <Lock className="h-3 w-3" />
                      )}
                      Lock
                    </Button>
                  </div>
                )}

                {/* View Details Link */}
                <Link
                  href={`/iot-devices/${device.id}`}
                  className="block w-full text-center text-sm text-blue-600 hover:text-blue-800 pt-2 border-t"
                >
                  View Details →
                </Link>
              </CardContent>
            </Card>
          ))}
        </div>

        {devices.length === 0 && (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-12">
              <Monitor className="h-12 w-12 text-muted-foreground mb-4" />
              <h3 className="text-lg font-semibold mb-2">No IoT Devices Found</h3>
              <p className="text-muted-foreground text-center">
                Connect your ESP32 devices to see them appear here.
              </p>
            </CardContent>
          </Card>
        )}
      </div>
    </AppLayout>
  );
};

export default IoTDevicesPage;
