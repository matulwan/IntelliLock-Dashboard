import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
  Key, 
  Users,
  CheckCircle,
  AlertCircle,
  Edit,
  Trash2,
  MoreVertical,
  RefreshCw
} from 'lucide-react';
import { motion } from 'framer-motion';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

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
  const [editingKey, setEditingKey] = useState<LabKey | null>(null);
  const [deleteConfirm, setDeleteConfirm] = useState<number | null>(null);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [lastUpdate, setLastUpdate] = useState(new Date());

  // Auto-refresh every 3 seconds for real-time updates
  useEffect(() => {
    const interval = setInterval(() => {
      refreshData();
    }, 3000); // Refresh every 3 seconds

    return () => clearInterval(interval);
  }, []);

  const refreshData = () => {
    if (isRefreshing) return;
    
    setIsRefreshing(true);
    router.reload({
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        setIsRefreshing(false);
        setLastUpdate(new Date());
      }
    });
  };

  const getKeyStatusBadge = (status: string) => {
    if (status === 'available') {
      return (
        <Badge className="bg-green-100 text-green-800 hover:bg-green-100">
          <CheckCircle className="h-3 w-3 mr-1" />
          Available
        </Badge>
      );
    } else if (status === 'checked_out') {
      return (
        <Badge className="bg-orange-100 text-orange-800 hover:bg-orange-100">
          <AlertCircle className="h-3 w-3 mr-1" />
          Checked Out
        </Badge>
      );
    } else {
      return (
        <Badge className="bg-gray-100 text-gray-800 hover:bg-gray-100">
          <AlertCircle className="h-3 w-3 mr-1" />
          {status}
        </Badge>
      );
    }
  };

  // Edit key function
  const handleEditKey = (key: LabKey) => {
    setEditingKey(key);

    const keyName = window.prompt('Key name', key.name);
    if (keyName === null) {
      setEditingKey(null);
      return;
    }

    const description = window.prompt('Description', key.description || '');
    if (description === null) {
      setEditingKey(null);
      return;
    }

    const location = window.prompt('Location', key.location || '');
    if (location === null) {
      setEditingKey(null);
      return;
    }

    const status = window.prompt('Status (available / checked_out)', key.status);
    if (status === null) {
      setEditingKey(null);
      return;
    }

    const normalizedStatus = status.trim().toLowerCase();
    if (!['available', 'checked_out'].includes(normalizedStatus)) {
      alert('Status must be "available" or "checked_out".');
      setEditingKey(null);
      return;
    }

    const rfid = window.prompt('RFID UID (leave blank to unset)', key.rfid || '') || '';

    router.put(route('key-management.update', key.id), {
      key_name: keyName.trim(),
      description: description.trim() || null,
      location: location.trim() || null,
      status: normalizedStatus,
      key_rfid_uid: rfid.trim() || null,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setEditingKey(null);
        refreshData(); // Refresh after edit
      },
      onError: () => setEditingKey(null),
    });
  };

  // Delete key function
  const handleDeleteKey = (keyId: number) => {
    if (deleteConfirm === keyId) {
      router.delete(route('key-management.destroy', keyId), {
        preserveScroll: true,
        onSuccess: () => {
          setDeleteConfirm(null);
          refreshData(); // Refresh after delete
        },
        onError: () => setDeleteConfirm(null),
      });
    } else {
      setDeleteConfirm(keyId);
      // Auto-cancel delete confirmation after 3 seconds
      setTimeout(() => {
        setDeleteConfirm(null);
      }, 3000);
    }
  };

  // Cancel delete confirmation
  const cancelDelete = () => {
    setDeleteConfirm(null);
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
          <div className="flex items-center gap-4">
            <div className="text-sm text-muted-foreground">
              Last updated: {lastUpdate.toLocaleTimeString()}
            </div>
            <Button 
              onClick={refreshData}
              variant="outline"
              size="sm"
              disabled={isRefreshing}
              className="flex items-center gap-2"
            >
              <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
              {isRefreshing ? 'Refreshing...' : 'Refresh'}
            </Button>
          </div>
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
                <p className="text-xs text-muted-foreground">Registered in system</p>
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
                <p className="text-xs text-muted-foreground">Ready for checkout</p>
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
                <p className="text-xs text-muted-foreground">Currently checked out</p>
              </CardContent>
            </Card>
          </motion.div>
        </motion.div>

        {/* Keys List */}
        <motion.div variants={item}>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>Lab Keys ({labKeys.length})</CardTitle>
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <div className="flex items-center gap-1">
                  <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                  Available: {keyBoxStatus.availableKeys}
                </div>
                <div className="flex items-center gap-1">
                  <div className="w-2 h-2 bg-orange-500 rounded-full"></div>
                  Checked Out: {keyBoxStatus.checkedOutKeys}
                </div>
              </div>
            </CardHeader>
            <CardContent>
            {labKeys.length === 0 ? (
              <div className="text-center py-8 text-muted-foreground">
                <Key className="h-12 w-12 mx-auto mb-2 opacity-50" />
                <p>No keys registered yet</p>
                <p className="text-sm">Keys will appear here when they are scanned</p>
              </div>
            ) : (
              <div className="space-y-3">
                {labKeys.map((key) => (
                  <motion.div 
                    key={key.id} 
                    className="flex items-center justify-between p-4 border rounded-lg hover:bg-accent/50 transition-colors group"
                    whileHover={{ scale: 1.01 }}
                    transition={{ duration: 0.2 }}
                  >
                    <div className="flex items-center gap-4 flex-1">
                      <div className={`p-3 rounded-lg ${
                        key.status === 'available' 
                          ? 'bg-green-100 border border-green-200' 
                          : 'bg-orange-100 border border-orange-200'
                      }`}>
                        <Key className={`h-5 w-5 ${
                          key.status === 'available' 
                            ? 'text-green-600' 
                            : 'text-orange-600'
                        }`} />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                          <h3 className="font-semibold truncate">{key.name}</h3>
                          {getKeyStatusBadge(key.status)}
                        </div>
                        {key.description && (
                          <p className="text-sm text-muted-foreground mb-1">{key.description}</p>
                        )}
                        <div className="flex items-center gap-4 text-xs text-muted-foreground">
                          <span className="font-mono bg-gray-100 px-2 py-1 rounded">
                            RFID: {key.rfid || 'Not set'}
                          </span>
                          <span>Location: {key.location}</span>
                        </div>
                        {key.status === 'checked_out' && key.holder && key.holder !== 'Available' && (
                          <div className="flex items-center gap-1 mt-2 text-sm">
                            <Users className="h-3 w-3 text-orange-500" />
                            <span className="text-orange-600 font-medium">Held by: {key.holder}</span>
                          </div>
                        )}
                      </div>
                    </div>
                    
                    <div className="flex items-center gap-3">
                      {/* Edit/Delete Dropdown */}
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="sm" className="h-8 w-8 p-0 opacity-0 group-hover:opacity-100 transition-opacity">
                            <MoreVertical className="h-4 w-4" />
                            <span className="sr-only">Open menu</span>
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem 
                            onClick={() => handleEditKey(key)}
                            className="flex items-center gap-2 cursor-pointer"
                            disabled={editingKey !== null}
                          >
                            <Edit className="h-4 w-4" />
                            Edit Key
                          </DropdownMenuItem>
                          <DropdownMenuItem 
                            onClick={() => handleDeleteKey(key.id)}
                            className="flex items-center gap-2 text-red-600 cursor-pointer"
                            disabled={deleteConfirm !== null}
                          >
                            <Trash2 className="h-4 w-4" />
                            Delete Key
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>

                      {/* Delete Confirmation */}
                      {deleteConfirm === key.id && (
                        <motion.div 
                          initial={{ opacity: 0, scale: 0.8 }}
                          animate={{ opacity: 1, scale: 1 }}
                          className="flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg p-2"
                        >
                          <span className="text-sm text-red-700 font-medium">Delete?</span>
                          <Button 
                            variant="destructive" 
                            size="sm" 
                            onClick={() => handleDeleteKey(key.id)}
                            className="h-6 px-2 text-xs"
                          >
                            Yes
                          </Button>
                          <Button 
                            variant="ghost" 
                            size="sm" 
                            onClick={cancelDelete}
                            className="h-6 px-2 text-xs"
                          >
                            No
                          </Button>
                        </motion.div>
                      )}
                    </div>
                  </motion.div>
                ))}
              </div>
            )}
            </CardContent>
          </Card>
        </motion.div>

        {/* Recent Transactions */}
        {recentTransactions.length > 0 && (
          <motion.div variants={item}>
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  Recent Transactions
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  {recentTransactions.map((transaction) => (
                    <div key={transaction.id} className="flex items-center justify-between p-3 border rounded-lg">
                      <div className="flex items-center gap-3">
                        <div className={`p-2 rounded-full ${
                          transaction.action === 'checkout' 
                            ? 'bg-orange-100 text-orange-600' 
                            : 'bg-green-100 text-green-600'
                        }`}>
                          {transaction.action === 'checkout' ? (
                            <span className="text-sm font-bold">→</span>
                          ) : (
                            <span className="text-sm font-bold">←</span>
                          )}
                        </div>
                        <div>
                          <p className="font-medium">
                            {transaction.key_name} - {transaction.user_name}
                          </p>
                          <p className="text-sm text-muted-foreground capitalize">
                            {transaction.action} • {transaction.time}
                          </p>
                        </div>
                      </div>
                      <p className="text-sm text-muted-foreground">{transaction.formatted_time}</p>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </motion.div>
        )}

        {/* Active Alerts */}
        {activeAlerts.length > 0 && (
          <motion.div variants={item}>
            <Card className="border-orange-200 bg-orange-50">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-orange-900">
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
                        alert.severity === 'critical' ? 'bg-red-100 text-red-800 hover:bg-red-100' :
                        alert.severity === 'high' ? 'bg-orange-100 text-orange-800 hover:bg-orange-100' :
                        alert.severity === 'medium' ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-100' :
                        'bg-blue-100 text-blue-800 hover:bg-blue-100'
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