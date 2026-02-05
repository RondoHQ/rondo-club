# CardDAV Sync

Stadion includes a built-in CardDAV server that allows you to sync your contacts with apps like Apple Contacts, Android Contacts (via DAVx5), Thunderbird, and other CardDAV-compatible applications.

## Overview

CardDAV is an address book client/server protocol designed to allow users to access and share contact data on a server. It's supported natively by Apple devices and can be added to Android and other platforms using third-party apps.

## Setting Up CardDAV Sync

### Step 1: Create an App Password

For security reasons, CardDAV uses WordPress Application Passwords instead of your main account password.

1. Log in to Stadion
2. Go to **Settings**
3. Find the **CardDAV Sync** section
4. Enter a name for your app password (e.g., "iPhone Contacts")
5. Click **Create Password**
6. **Copy the generated password immediately** - it will only be shown once

### Step 2: Get Your Connection Details

In the CardDAV Sync section of Settings, you'll find:

- **Server URL**: The CardDAV server address
- **Username**: Your Stadion username

### Step 3: Configure Your Device

#### Apple Contacts (macOS)

1. Open **System Preferences** → **Internet Accounts**
2. Click **Add Other Account** → **CardDAV account**
3. Enter:
   - **Account Type**: Manual
   - **Username**: Your Stadion username
   - **Password**: Your app password
   - **Server Address**: The server URL from Settings
4. Click **Sign In**

#### Apple Contacts (iOS/iPadOS)

1. Open **Settings** → **Contacts** → **Accounts**
2. Tap **Add Account** → **Other** → **Add CardDAV Account**
3. Enter:
   - **Server**: The server URL from Settings
   - **Username**: Your Stadion username
   - **Password**: Your app password
4. Tap **Next**

#### Android (using DAVx5)

1. Install [DAVx5](https://www.davx5.com/) from Google Play or F-Droid
2. Open DAVx5 and tap **+** to add a new account
3. Select **Login with URL and user name**
4. Enter:
   - **Base URL**: The server URL from Settings
   - **User name**: Your Stadion username
   - **Password**: Your app password
5. Tap **Login**
6. Select your address book to sync

#### Thunderbird

1. Install the [TbSync](https://addons.thunderbird.net/en-US/thunderbird/addon/tbsync/) add-on
2. Install the [Provider for CalDAV & CardDAV](https://addons.thunderbird.net/en-US/thunderbird/addon/dav-4-tbsync/) add-on
3. Open **Tools** → **TbSync** (or **Extras** → **TbSync** on some systems)
4. Click **Account actions** → **Add new account** → **CalDAV & CardDAV**
5. Select **Manual configuration**
6. Enter:
   - **Account name**: Stadion (or any name you prefer)
   - **User name**: Your Stadion username
   - **CardDAV server address**: The server URL from Settings
7. Click **Add account**
8. Enter your app password when prompted
9. Select the address book to sync

## Technical Details

### URL Structure

| Endpoint | URL |
|----------|-----|
| Server root | `https://your-site.com/carddav/` |
| Principal | `https://your-site.com/carddav/principals/{username}/` |
| Address book | `https://your-site.com/carddav/addressbooks/{username}/contacts/` |

### Authentication

- **Method**: HTTP Basic Authentication
- **Username**: Your Stadion username
- **Password**: A WordPress Application Password (not your main account password)

### Sync Features

- **Bidirectional sync**: Changes made in Stadion appear in your devices, and vice versa
- **Sync tokens**: Efficient incremental sync - only changed contacts are transferred
- **Conflict resolution**: Most recent change wins

### Supported Data

The following contact fields are synced:

- First name, infix (tussenvoegsel), last name, nickname
- Email addresses
- Phone numbers (mobile, work, home)
- Physical addresses (street, city, state/province, postal code, country)
- Team and job title (from current work history)
- Birthday (from important dates)
- Profile photo
- URLs (website, LinkedIn, Twitter, etc.)

### Data Access

- Each user only sees their own contacts
- CardDAV access respects the same permissions as the Stadion web interface
- Admins cannot access other users' contacts via CardDAV

## Security Best Practices

1. **Use unique app passwords**: Create a separate app password for each device
2. **Revoke unused passwords**: Remove app passwords for devices you no longer use
3. **Use HTTPS**: CardDAV connections should always use HTTPS (enforced by default)
4. **Monitor usage**: Check the "Last used" date to identify inactive app passwords

## Troubleshooting

### "Authentication failed"

- Verify you're using an app password from Settings, not your main account password
- Check that the username matches your Stadion username exactly (case-sensitive)
- Ensure the server URL is correct and includes the full path
- WordPress 6.8+ uses BLAKE2b hashing for app passwords - ensure your site is updated

### "Connection refused" or "Server not found"

- Verify the server URL is correct
- Check that your site is accessible and HTTPS is working
- Ensure no firewall is blocking the connection

### Contacts not appearing

- Wait a few minutes for the initial sync to complete
- Check that you have contacts in Stadion
- Try forcing a sync in your CardDAV app

### Sync conflicts

- The most recently modified version of a contact is kept
- If you edit the same contact on multiple devices simultaneously, changes from one device may be overwritten

## Backend Implementation

The CardDAV server uses [sabre/dav](https://sabre.io/dav/), a popular open-source WebDAV/CardDAV/CalDAV library. Key components:

- **Authentication**: WordPress Application Passwords verified via `wp_verify_fast_hash()` (WordPress 6.8+ BLAKE2b) with fallback to `wp_check_password()` for older versions
- **Principal Backend**: Maps WordPress users to DAV principals
- **CardDAV Backend**: CRUD operations on Person custom post type
- **Sync Support**: Change tracking with sync tokens for efficient updates

For more details, see the source code in `includes/carddav/`.

