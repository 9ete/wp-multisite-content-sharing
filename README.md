# WP Multisite Content Sharing

The **WP Multisite Content Sharing** plugin allows WordPress Multisite network administrators to mirror posts across different sites within the network. This feature helps maintain consistent content across multiple sites without manual duplication.

---

## Features

- **Content Mirroring:** Copy posts from a source site to one or more destination sites in the network.
- **Manual Configuration:** Specify source and destination sites directly in the configuration.
- **Automated Synchronization:** Set up cron jobs for automatic content synchronization.

---

## Installation

1. **Download the Plugin:**  
   Clone or download the plugin from the [GitHub repository](https://github.com/9ete/wp-multisite-content-sharing).

2. **Upload Files:**  
   Upload the plugin files to your WordPress installation directory at `/wp-content/plugins/`.

3. **Activate the Plugin:**  
   Go to the WordPress dashboard, navigate to **Plugins**, and activate the plugin.

---

## Configuration

### 1. Define Source and Destination Sites

Add the following code to your `wp-config.php` file:

```php
define('WP_MULTISITE_CONTENT_SHARING_SOURCE', 1); // Replace '1' with the source site ID
define('WP_MULTISITE_CONTENT_SHARING_DESTINATION', 2); // Replace '2' with the destination site ID
```

- `WP_MULTISITE_CONTENT_SHARING_SOURCE`: The site ID from which content will be copied.
- `WP_MULTISITE_CONTENT_SHARING_DESTINATION`: The site ID where content will be mirrored.

### 2. Enable Automatic Synchronization

To automate the synchronization process, add this line to your `wp-config.php` file:

```php
define('WP_MULTISITE_CONTENT_SHARING_CRON', true);
```

This will schedule the synchronization process to run automatically.

---

## Usage Considerations

1. **Consistent Themes and Plugins:**  
   Ensure both the source and destination sites use compatible themes and have the required plugins activated.

2. **Content Management:**  
   Monitor the content being mirrored to avoid duplication or conflicts, especially with custom post types or taxonomies.

3. **Testing:**  
   Perform thorough testing in a staging environment before using the plugin on a live network.

---
