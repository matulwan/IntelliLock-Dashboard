import React from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
  Key, 
  Wifi, 
  WifiOff, 
  Users,
  Clock,
  CheckCircle,
  AlertCircle
} from 'lucide-react';

const KeyManagementPage: React.FC = () => {
  // Mock data - in real app, this would come from props
  const keyBoxStatus = {
    status: 'online',
    location: 'Main Lab Office',
    lastSeen: '2 minutes ago',
    totalKeys: 5,
    availableKeys: 3,
    checkedOutKeys: 2
  };

  const labKeys = [
    { id: 1, name: 'Lab A', description: 'Chemistry Lab', status: 'available', rfid: 'LABA001' },
    { id: 2, name: 'Lab B', description: 'Physics Lab', status: 'checked_out', rfid: 'LABB002', holder: 'John Doe' },
    { id: 3, name: 'Lab C', description: 'Biology Lab', status: 'available', rfid: 'LABC003' },
    { id: 4, name: 'Lab D', description: 'Computer Lab', status: 'checked_out', rfid: 'LABD004', holder: 'Jane Smith' },
    { id: 5, name: 'Lab E', description: 'Research Lab', status: 'available', rfid: 'LABE005' }
  ];

  const getStatusIcon = (status: string) => {
    return status === 'online' ? 
      <Wifi className="h-4 w-4 text-green-500" /> : 
      <WifiOff className="h-4 w-4 text-gray-500" />;
  };

  const getKeyStatusBadge = (status: string) => {
    return status === 'available' ? (
      <Badge className="bg-green-100 text-green-800">
        <CheckCircle className="h-3 w-3 mr-1" />
        Available
      </Badge>
    ) : (
      <Badge className="bg-orange-100 text-orange-800">
        <AlertCircle className="h-3 w-3 mr-1" />
        Checked Out
      </Badge>
    );
  };

  return (
    <AppLayout>
      <Head title="Key Management" />
      
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Key Management</h1>
          <p className="text-muted-foreground">
            Monitor your smart lab key box and track key usage
          </p>
        </div>

        {/* Key Box Status */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              {getStatusIcon(keyBoxStatus.status)}
              Smart Key Box Status
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-4">
              <div className="space-y-2">
                <p className="text-sm font-medium text-muted-foreground">Location</p>
                <p className="text-lg font-semibold">{keyBoxStatus.location}</p>
              </div>
              <div className="space-y-2">
                <p className="text-sm font-medium text-muted-foreground">Last Seen</p>
                <p className="text-lg font-semibold flex items-center gap-1">
                  <Clock className="h-4 w-4" />
                  {keyBoxStatus.lastSeen}
                </p>
              </div>
              <div className="space-y-2">
                <p className="text-sm font-medium text-muted-foreground">Available Keys</p>
                <p className="text-lg font-semibold text-green-600">
                  {keyBoxStatus.availableKeys} / {keyBoxStatus.totalKeys}
                </p>
              </div>
              <div className="space-y-2">
                <p className="text-sm font-medium text-muted-foreground">Checked Out</p>
                <p className="text-lg font-semibold text-orange-600">
                  {keyBoxStatus.checkedOutKeys}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Stats Cards */}
        <div className="grid gap-4 md:grid-cols-3">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Keys</CardTitle>
              <Key className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{keyBoxStatus.totalKeys}</div>
            </CardContent>
          </Card>
          
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Available</CardTitle>
              <CheckCircle className="h-4 w-4 text-green-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-green-600">{keyBoxStatus.availableKeys}</div>
            </CardContent>
          </Card>
          
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">In Use</CardTitle>
              <Users className="h-4 w-4 text-orange-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-orange-600">{keyBoxStatus.checkedOutKeys}</div>
            </CardContent>
          </Card>
        </div>

        {/* Keys List */}
        <Card>
          <CardHeader>
            <CardTitle>Lab Keys</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {labKeys.map((key) => (
                <div key={key.id} className="flex items-center justify-between p-4 border rounded-lg">
                  <div className="flex items-center gap-4">
                    <div className="p-2 bg-blue-100 rounded-lg">
                      <Key className="h-5 w-5 text-blue-600" />
                    </div>
                    <div>
                      <h3 className="font-semibold">{key.name}</h3>
                      <p className="text-sm text-muted-foreground">{key.description}</p>
                      <p className="text-xs text-muted-foreground font-mono">RFID: {key.rfid}</p>
                    </div>
                  </div>
                  
                  <div className="text-right">
                    {getKeyStatusBadge(key.status)}
                    {key.status === 'checked_out' && key.holder && (
                      <p className="text-sm text-muted-foreground mt-1">
                        Holder: {key.holder}
                      </p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Instructions */}
        <Card>
          <CardHeader>
            <CardTitle>How It Works</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <h4 className="font-semibold mb-2">📱 Access the Key Box</h4>
                <p className="text-sm text-muted-foreground">
                  Use your RFID card or fingerprint to unlock the smart key box. 
                  The box will remain unlocked for 10 seconds.
                </p>
              </div>
              
              <div>
                <h4 className="font-semibold mb-2">🔑 Take/Return Keys</h4>
                <p className="text-sm text-muted-foreground">
                  Each lab key has an RFID tag. The system automatically tracks 
                  when keys are taken or returned to the box.
                </p>
              </div>
              
              <div>
                <h4 className="font-semibold mb-2">📊 Real-time Tracking</h4>
                <p className="text-sm text-muted-foreground">
                  All key transactions are logged in real-time. Check the Access Logs 
                  page for detailed history.
                </p>
              </div>
              
              <div>
                <h4 className="font-semibold mb-2">👥 User Management</h4>
                <p className="text-sm text-muted-foreground">
                  Add users with RFID/fingerprint access through the User Management 
                  page. Enable "IoT Access" for key box permissions.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
};

export default KeyManagementPage;
