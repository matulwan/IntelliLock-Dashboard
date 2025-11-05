import React from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
  Key, 
  Wifi, 
  WifiOff, 
  Users,
  Clock,
  CheckCircle,
  AlertCircle,
  Activity,
  AlertTriangle,
  ArrowDownCircle,
  ArrowUpCircle
} from 'lucide-react';
import { motion } from 'framer-motion';

interface KeyBoxStatus {
  status: string;
  location: string;
  last_seen: string;
  ip_address: string;
  totalKeys: number;
  availableKeys: number;
  checkedOutKeys: number;
}

interface LabKey {
  id: number;
  name: string;
  description: string;
  status: string;
  rfid: string;
  holder: string | null;
  location: string;
}

interface Transaction {
  id: number;
  key_name: string;
  user_name: string;
  action: string;
  time: string;
  formatted_time: string;
}

interface Alert {
  id: number;
  type: string;
  severity: string;
  title: string;
  description: string;
  time: string;
}

interface KeyManagementPageProps {
  keyBoxStatus: KeyBoxStatus;
  labKeys: LabKey[];
  recentTransactions: Transaction[];
  activeAlerts: Alert[];
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Key Management',
    href: '/key-management',
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

const KeyManagementPage: React.FC<KeyManagementPageProps> = ({ 
  keyBoxStatus, 
  labKeys, 
  recentTransactions,
  activeAlerts 
}) => {

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'online':
        return <Wifi className="h-4 w-4 text-green-500" />;
      case 'offline':
        return <WifiOff className="h-4 w-4 text-gray-500" />;
      case 'error':
        return <AlertTriangle className="h-4 w-4 text-red-500" />;
      default:
        return <WifiOff className="h-4 w-4 text-gray-400" />;
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'online':
        return <Badge className="bg-green-100 text-green-800">Online</Badge>;
      case 'offline':
        return <Badge className="bg-gray-100 text-gray-800">Offline</Badge>;
      case 'error':
        return <Badge className="bg-red-100 text-red-800">Error</Badge>;
      default:
        return <Badge className="bg-gray-100 text-gray-800">Unknown</Badge>;
    }
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
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Key Management" />
      
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
            Key Management
          </motion.h1>
          {getStatusBadge(keyBoxStatus.status)}
        </motion.div>

        {/* Stats Cards */}
        <motion.div variants={container} className="grid gap-6 md:grid-cols-3">
          <motion.div variants={item} whileHover={cardHover}>
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total Keys</CardTitle>
                <Key className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{keyBoxStatus.totalKeys}</div>
              </CardContent>
            </Card>
          </motion.div>
          
          <motion.div variants={item} whileHover={cardHover}>
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Available</CardTitle>
                <CheckCircle className="h-4 w-4 text-green-500" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-green-600">{keyBoxStatus.availableKeys}</div>
              </CardContent>
            </Card>
          </motion.div>
          
          <motion.div variants={item} whileHover={cardHover}>
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">In Use</CardTitle>
                <Users className="h-4 w-4 text-orange-500" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-orange-600">{keyBoxStatus.checkedOutKeys}</div>
              </CardContent>
            </Card>
          </motion.div>
        </motion.div>

        {/* Keys List */}
        <motion.div variants={item} whileHover={cardHover}>
          <Card>
            <CardHeader>
              <CardTitle>Lab Keys</CardTitle>
            </CardHeader>
            <CardContent>
            {labKeys.length === 0 ? (
              <div className="text-center py-8 text-muted-foreground">
                <Key className="h-12 w-12 mx-auto mb-2 opacity-50" />
                <p>No keys registered yet</p>
                <p className="text-sm">Keys will appear here when they are scanned</p>
              </div>
            ) : (
              <div className="space-y-4">
                {labKeys.map((key) => (
                  <div key={key.id} className="flex items-center justify-between p-4 border rounded-lg hover:bg-accent/50 transition-colors">
                    <div className="flex items-center gap-4">
                      <div className={`p-2 rounded-lg ${
                        key.status === 'available' 
                          ? 'bg-green-100' 
                          : 'bg-orange-100'
                      }`}>
                        <Key className={`h-5 w-5 ${
                          key.status === 'available' 
                            ? 'text-green-600' 
                            : 'text-orange-600'
                        }`} />
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
                          <Users className="h-3 w-3 inline mr-1" />
                          {key.holder}
                        </p>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
            </CardContent>
          </Card>
        </motion.div>

        {/* Recent Transactions */}
        <motion.div variants={item} whileHover={cardHover}>
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Activity className="h-5 w-5" />
                Recent Transactions
              </CardTitle>
            </CardHeader>
            <CardContent>
              {recentTransactions.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  <Activity className="h-12 w-12 mx-auto mb-2 opacity-50" />
                  <p>No transactions yet</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {recentTransactions.map((transaction) => (
                    <div key={transaction.id} className="flex items-center justify-between p-3 border rounded-lg">
                      <div className="flex items-center gap-3">
                        {transaction.action === 'checkout' ? (
                          <ArrowDownCircle className="h-5 w-5 text-orange-500" />
                        ) : (
                          <ArrowUpCircle className="h-5 w-5 text-green-500" />
                        )}
                        <div>
                          <p className="font-medium">{transaction.key_name}</p>
                          <p className="text-sm text-muted-foreground">
                            {transaction.action === 'checkout' ? 'Checked out' : 'Returned'} by {transaction.user_name}
                          </p>
                        </div>
                      </div>
                      <div className="text-right">
                        <p className="text-sm text-muted-foreground">{transaction.time}</p>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </motion.div>

        {/* Active Alerts */}
        {activeAlerts.length > 0 && (
          <motion.div variants={item}>
            <Card className="border-orange-200 bg-orange-50">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-orange-900">
                  <AlertTriangle className="h-5 w-5" />
                  Active Alerts
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  {activeAlerts.map((alert) => (
                    <div key={alert.id} className="flex items-start gap-3 p-3 bg-white border border-orange-200 rounded-lg">
                      <AlertCircle className={`h-5 w-5 mt-0.5 ${
                        alert.severity === 'critical' ? 'text-red-500' :
                        alert.severity === 'high' ? 'text-orange-500' :
                        alert.severity === 'medium' ? 'text-yellow-500' :
                        'text-blue-500'
                      }`} />
                      <div className="flex-1">
                        <p className="font-medium">{alert.title}</p>
                        <p className="text-sm text-muted-foreground">{alert.description}</p>
                        <p className="text-xs text-muted-foreground mt-1">{alert.time}</p>
                      </div>
                      <Badge className={`${
                        alert.severity === 'critical' ? 'bg-red-100 text-red-800' :
                        alert.severity === 'high' ? 'bg-orange-100 text-orange-800' :
                        alert.severity === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-blue-100 text-blue-800'
                      }`}>
                        {alert.severity}
                      </Badge>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </motion.div>
        )}

      </motion.div>
    </AppLayout>
  );
};

export default KeyManagementPage;
