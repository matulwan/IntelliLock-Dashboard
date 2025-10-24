import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft, Save, CreditCard, Fingerprint } from 'lucide-react';

const CreateAuthorizedUserPage: React.FC = () => {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
    rfid_uid: '',
    fingerprint_id: '',
    role: 'user',
    notes: ''
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/authorized-users');
  };

  return (
    <AppLayout>
      <Head title="Add Authorized User" />
      
      <div className="space-y-6">
        <div className="flex items-center gap-4">
          <Link href="/authorized-users">
            <Button variant="outline" size="sm">
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back to Users
            </Button>
          </Link>
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Add Authorized User</h1>
            <p className="text-muted-foreground">
              Create a new user with access to IoT devices
            </p>
          </div>
        </div>

        <div className="max-w-2xl">
          <Card>
            <CardHeader>
              <CardTitle>User Information</CardTitle>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-6">
                {/* Basic Information */}
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="name">Full Name *</Label>
                    <Input
                      id="name"
                      type="text"
                      value={data.name}
                      onChange={(e) => setData('name', e.target.value)}
                      placeholder="Enter full name"
                      className={errors.name ? 'border-red-500' : ''}
                    />
                    {errors.name && (
                      <p className="text-sm text-red-600">{errors.name}</p>
                    )}
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="email">Email Address</Label>
                    <Input
                      id="email"
                      type="email"
                      value={data.email}
                      onChange={(e) => setData('email', e.target.value)}
                      placeholder="Enter email address"
                      className={errors.email ? 'border-red-500' : ''}
                    />
                    {errors.email && (
                      <p className="text-sm text-red-600">{errors.email}</p>
                    )}
                  </div>
                </div>

                {/* Role Selection */}
                <div className="space-y-2">
                  <Label htmlFor="role">Role *</Label>
                  <Select value={data.role} onValueChange={(value) => setData('role', value)}>
                    <SelectTrigger className={errors.role ? 'border-red-500' : ''}>
                      <SelectValue placeholder="Select role" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="admin">Admin - Full access and management</SelectItem>
                      <SelectItem value="user">User - Standard access</SelectItem>
                      <SelectItem value="guest">Guest - Limited access</SelectItem>
                    </SelectContent>
                  </Select>
                  {errors.role && (
                    <p className="text-sm text-red-600">{errors.role}</p>
                  )}
                </div>

                {/* Access Methods */}
                <div className="space-y-4">
                  <h3 className="text-lg font-semibold flex items-center gap-2">
                    Access Methods
                    <span className="text-sm font-normal text-muted-foreground">
                      (At least one method required)
                    </span>
                  </h3>
                  
                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="rfid_uid" className="flex items-center gap-2">
                        <CreditCard className="h-4 w-4" />
                        RFID Card UID
                      </Label>
                      <Input
                        id="rfid_uid"
                        type="text"
                        value={data.rfid_uid}
                        onChange={(e) => setData('rfid_uid', e.target.value.toUpperCase())}
                        placeholder="e.g., 14B13C03"
                        className={errors.rfid_uid ? 'border-red-500' : ''}
                        maxLength={8}
                      />
                      <p className="text-xs text-muted-foreground">
                        8-character hexadecimal UID from RFID card
                      </p>
                      {errors.rfid_uid && (
                        <p className="text-sm text-red-600">{errors.rfid_uid}</p>
                      )}
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="fingerprint_id" className="flex items-center gap-2">
                        <Fingerprint className="h-4 w-4" />
                        Fingerprint ID
                      </Label>
                      <Input
                        id="fingerprint_id"
                        type="number"
                        value={data.fingerprint_id}
                        onChange={(e) => setData('fingerprint_id', e.target.value)}
                        placeholder="e.g., 1"
                        className={errors.fingerprint_id ? 'border-red-500' : ''}
                        min="1"
                        max="127"
                      />
                      <p className="text-xs text-muted-foreground">
                        ID number from fingerprint enrollment (1-127)
                      </p>
                      {errors.fingerprint_id && (
                        <p className="text-sm text-red-600">{errors.fingerprint_id}</p>
                      )}
                    </div>
                  </div>
                </div>

                {/* Notes */}
                <div className="space-y-2">
                  <Label htmlFor="notes">Notes</Label>
                  <Textarea
                    id="notes"
                    value={data.notes}
                    onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('notes', e.target.value)}
                    placeholder="Additional notes about this user..."
                    rows={3}
                    className={errors.notes ? 'border-red-500' : ''}
                  />
                  {errors.notes && (
                    <p className="text-sm text-red-600">{errors.notes}</p>
                  )}
                </div>

                {/* Submit Buttons */}
                <div className="flex items-center gap-4 pt-4">
                  <Button type="submit" disabled={processing} className="flex items-center gap-2">
                    <Save className="h-4 w-4" />
                    {processing ? 'Creating...' : 'Create User'}
                  </Button>
                  <Link href="/authorized-users">
                    <Button type="button" variant="outline">
                      Cancel
                    </Button>
                  </Link>
                </div>
              </form>
            </CardContent>
          </Card>

          {/* Help Card */}
          <Card className="mt-6">
            <CardHeader>
              <CardTitle className="text-lg">How to Get Access Credentials</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <h4 className="font-semibold flex items-center gap-2">
                  <CreditCard className="h-4 w-4" />
                  RFID Card UID
                </h4>
                <p className="text-sm text-muted-foreground mt-1">
                  Present the RFID card to any ESP32 device. The UID will be displayed in the Serial Monitor 
                  and logged in the access logs even if the card is not yet authorized.
                </p>
              </div>
              
              <div>
                <h4 className="font-semibold flex items-center gap-2">
                  <Fingerprint className="h-4 w-4" />
                  Fingerprint ID
                </h4>
                <p className="text-sm text-muted-foreground mt-1">
                  Use the ESP32 fingerprint enrollment feature to register a new fingerprint. 
                  The system will assign an ID number (1-127) which you can use here.
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </AppLayout>
  );
};

export default CreateAuthorizedUserPage;
