#!/usr/bin/env python

import os, sys, time, re
import MySQLdb, ConfigParser

import bcrypt

import Ice
Ice.loadSlice("--all -I/usr/share/Ice-3.5.1/slice/ /usr/share/slice/Murmur.ice")
import Murmur

# -------------------------------------------------------------------------------

server_id = 1

sql_name = ""
sql_user = ""
sql_pass = ""
sql_host = "127.0.0.1"

# -------------------------------------------------------------------------------

try:
    db = MySQLdb.connect(sql_host, sql_user, sql_pass, sql_name)
    db.close()
except Exception, e:
    print("Database intitialization failed: {0}".format(e))
    sys.exit(0)

# -------------------------------------------------------------------------------

class ServerAuthenticatorI(Murmur.ServerUpdatingAuthenticator):
	global server
	def __init__(self, server, adapter):
		self.server = server

	def authenticate(self, name, pw, certificates, certhash, cerstrong, out_newname):
	    try:
		db = MySQLdb.connect(sql_host, sql_user, sql_pass, sql_name)

# ---- Verify Params

		if(not name or len(name) == 0):
			return (-1, None, None)

		print("Info: Trying '{0}'").format(name)

		if(not pw or len(pw) == 0):
			print("Fail: {0} did not send a passsword").format(name)
			return (-1, None, None)

# ---- Retrieve User

		c = db.cursor(MySQLdb.cursors.DictCursor)
		c.execute("SELECT * FROM users WHERE username = %s", (name))
		row = c.fetchone()

		if not row:
			print("Fail: {0} not found in database").format(name)
			return (-1, None, None)

		id = row['id']
		display_name = row['display_name']
		character_id = row['character_id']
		character_name = row['character_name']
		corporation_id = row['corporation_id']
		corporation_name = row['corporation_name']
		alliance_id = row['alliance_id']
		alliance_name = row['alliance_name']
		password = row['password']

# ---- Retrieve Groups

		groups = []
		
		c.execute("SELECT * FROM `users_groups` WHERE `user_id` = %s", (id))
		results = c.fetchall()
		group_ids = []
		print("starting for loop")
		for row in results:
			c.execute("SELECT `name` FROM `user_groups` WHERE `id` = %s", (row["user_group_id"]))
			result = c.fetchone()
			groups.append("%s" % (result['name']))


		c.close()

# ---- Verify Password

		if bcrypt.checkpw(pw, "$2a" + password[3:]) == False:
		    print("Fail: {0} password does not match for {1}").format(name, character_id)
		    return (-1, None, None)

# ---- Done
		if character_id == None:
		    return (-1, None, None)
		print("Success: '{0}' as '{1}' in {2}").format(character_id, display_name, groups)
		return (character_id, display_name, groups)

	    except Exception, e:
		print("Fail: {0}".format(e))
		return (-1, None, None)
	    finally:
		if db:
		    db.close()

	def createChannel(name, server, id):
		return -2

	def getRegistration(self, id, current=None):
	    return (-2, None, None)

	def registerPlayer(self, name, current=None):
	    print ("Warn: Somebody tried to register player '{0}'").format(name)
	    return -1

	def unregisterPlayer(self, id, current=None):
	    print ("Warn: Somebody tried to unregister player '{0}'").format(id)
	    return -1

	def getRegisteredUsers(self, filter, current=None):
	    return dict()

	def registerUser(self, name, current = None):
	    print ("Warn: Somebody tried to register user '{0}'").format(name)
	    return -1

	def unregisterUser(self, name, current = None):
	    print ("Warn: Somebody tried to unregister user '{0}'").format(name)
	    return -1

	def idToTexture(self, id, current=None):
		return None

	def idToName(self, id, current=None):
		return None

	def nameToId(self, name, current=None):
		return id

	def getInfo(self, id, current = None):
		return (False, None)

	def setInfo(self, id, info, current = None):
	    print ("Warn: Somebody tried to set info for '{0}'").format(id)
	    return -1

	def setTexture(self, id, texture, current = None):
	    print ("Warn: Somebody tried to set a texture for '{0}'").format(id)
	    return -1

# -------------------------------------------------------------------------------

if __name__ == "__main__":
    print('Starting authenticator...')

    ice = Ice.initialize(sys.argv)
    meta = Murmur.MetaPrx.checkedCast(ice.stringToProxy('Meta:tcp -h 127.0.0.1 -p 6502'))
    adapter = ice.createObjectAdapterWithEndpoints("Callback.Client", "tcp -h 127.0.0.1")
    adapter.activate()

    for server in meta.getBootedServers():
		if(server.id() != server_id):
			continue

		print("Binding to server: {0} {1}".format(id, server))
		serverR = Murmur.ServerUpdatingAuthenticatorPrx.uncheckedCast(adapter.addWithUUID(ServerAuthenticatorI(server, adapter)))
		server.setAuthenticator(serverR)
		break
    try:
        ice.waitForShutdown()
    except KeyboardInterrupt:
        print 'Aborting!'

    ice.shutdown()
    print 'o7'
