=== UniVoucher For WooCommerce ===
Contributors: univoucher
Tags: crypto, gift cards, cryptocurrency, blockchain, vouchers
Requires at least: 5.0
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 1.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrate UniVoucher decentralized crypto gift cards with WooCommerce. Create and sell blockchain-based gift cards for any token.

== Description ==

UniVoucher For WooCommerce integrates the power of decentralized gift cards with your WooCommerce store. This plugin allows you to:

* **Sell UniVoucher gift cards** for any ERC-20 token or native cryptocurrency
* **Manage gift card inventory** with automatic stock synchronization
* **Bulk import gift cards** via CSV upload

= Key Features =

**🎫 Digital Gift Card Management**

* Add individual or bulk gift cards
* Automatic stock management integration with WooCommerce
* Real-time inventory tracking and updates
* Support for multiple blockchain networks

**🔐 Security & Encryption**

* Card secrets encrypted with industry-standard encryption
* Secure database storage with backup key management

**📊 Comprehensive Tracking**

* Dual status system (availability + card delivery tracking)
* Order integration with automatic card assignment
* Detailed analytics and reporting
* Order notes for all card operations

**🤖 product Automation**

* Automatic product content generation with customizable templates
* Dynamic product descriptions based on token and network data
* Automatic product image generation with customizable templates
* Drag-and-drop image customization interface
* Upload custom templates, fonts, and token logos

= Supported Networks =

The plugin supports gift cards on multiple blockchain networks including:

* Ethereum
* Base
* Polygon
* Arbitrum
* Optimism
* BNB Chain
* Avalanche

= Requirements =

* WordPress 5.0 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher

= Third-Party Services =

This plugin interacts with the following external services:

* **UniVoucher API** - For gift card validation and creation
* **Blockchain Networks** - For gift card transactions on various networks
* **Alchemy API** - For blockchain data retrieval

Please review the terms of service and privacy policies of these services before using this plugin.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/univoucher-for-woocommerce/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to UniVoucher > Settings to configure the plugin
4. Create your first UniVoucher-enabled product
5. Start adding gift cards to your inventory

== Frequently Asked Questions ==

= What is UniVoucher? =

UniVoucher is a decentralized platform for creating and managing cryptocurrency gift cards. It allows you to create gift cards for any ERC-20 token or native currency on supported blockchain networks.

= Do I need cryptocurrency knowledge to use this plugin? =

Basic understanding of cryptocurrency is helpful, but the plugin is designed to be user-friendly.

= Is this plugin free? =

Yes, the plugin is free to use. However, you'll need to create gift cards on the UniVoucher platform, which may have associated blockchain transaction fees.

= How secure are the gift cards? =

Gift card secrets are encrypted using industry-standard encryption and stored securely in your WordPress database. The plugin includes backup key management to prevent data loss.

= Can I import existing gift cards? =

Yes, you can bulk import gift cards using the CSV upload feature in the admin interface.

= Which blockchain networks are supported? =

The plugin supports Ethereum, Base, Polygon, Arbitrum, Optimism, BNB Chain, and Avalanche networks.

= Do I need WooCommerce? =

Yes, this plugin requires WooCommerce to be installed and active as it integrates directly with WooCommerce's product and order systems.


== Changelog ==

= 1.1.4 =
* Enhanced UniVoucher For WooCommerce with Internal Wallet functionality
* Added new method for card creation using an internal wallet
= 1.1.3 =
* Enhanced License Manager for WooCommerce integration with improved order processing
* Fixed order context setup for better email delivery and order details display
* Better handling of card creation date and abandoned date calculations

= 1.1.2 =
* Fixed email sender display issue - gift card delivery emails now use site name and admin email instead of "WordPress" as sender
* Added order auto-completion feature for UniVoucher products with configurable settings
* Enhanced order processing logic with inventory-based processing controls
* Added new admin settings for order auto-completion and inventory-based processing

= 1.1.1 =
* Improved gift card assignment behavior - now assigns all available cards instead of failing when insufficient stock
* Enhanced order logging with aggregated card assignment notes instead of individual card logs
* Fixed product settings bug that was preventing proper configuration
* Better handling of partial card assignments with clear visibility into unassigned cards
* Improved stock management when cards are insufficient for order quantities

= 1.1.0 =
* Enhanced email delivery system with customizable templates
* Improved order management and customer-facing gift card display
* Advanced inventory filtering by product
* Enhanced product management with safety features
* Improved image template customization interface
* Security and performance enhancements
* Updated WordPress compatibility (tested up to 6.8.2)

= 1.0.0 =
* Initial release
* Digital gift card management with UniVoucher integration
* Support for multiple blockchain networks (Ethereum, Base, Polygon, Arbitrum, Optimism, BNB Chain, Avalanche)
* Encrypted card storage with industry-standard security
* Automatic stock synchronization with WooCommerce
* Bulk CSV import functionality for gift cards
* Real-time inventory tracking and management
* Order integration with automatic card assignment
* Admin interface for card management and settings
* Comprehensive delivery status tracking
* Support for both ERC-20 tokens and native currencies
* Automatic product content generation with customizable templates
* Dynamic product image generation with drag-and-drop customization interface

== Upgrade Notice ==

= 1.1.4 =
Feature update with Internal Wallet functionality. This version introduces a new method for card creation using an internal wallet, along with associated admin settings and UI components.

= 1.1.3 =
Important update with enhanced License Manager for WooCommerce integration. This version improves order processing, email delivery, and admin order actions for UniVoucher cards. enhanced creation date handling.

= 1.1.2 =
Important update with order auto-completion feature and email sender fix. This version adds configurable order auto-completion for UniVoucher products, enhanced inventory-based processing controls, and fixes email sender display. Includes new admin settings for controlling order completion behavior.

= 1.1.1 =
Important update with improved gift card assignment behavior and enhanced order logging. This version fixes product settings issues and provides better handling of insufficient stock scenarios.

= 1.1.0 =
Important update with enhanced email delivery system, improved order management, advanced inventory filtering, and better product management. Includes security improvements and enhanced user interface.

= 1.0.0 =
Initial release of UniVoucher For WooCommerce. Install to start selling crypto gift cards in your WooCommerce store.

== Support ==

For support, documentation, and updates, visit:

* [UniVoucher Website](https://univoucher.com)
* [Plugin Documentation](https://docs.univoucher.com)
* [Support Forum](https://t.me/UniVoucherOfficial)

== Privacy Notice ==

This plugin stores gift card information in your WordPress database. Card secrets are encrypted for security. The plugin may connect to external services for blockchain interactions and API calls as described in the Third-Party Services section above. 