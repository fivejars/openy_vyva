# Open Y Virtual Y Video Automation

## Installation

### Prerequisites

You should have Open Y Virtual Y modules installed. See https://github.com/ymcatwincities/openy_gated_content.

### With Composer 2+

With Composer 2+ the installation should be as simple as `composer require fivejars/vyva`.

### With Composer 1.x
Try installing it with `composer require fivejars/vyva`.

If it fails, it means that the package is not going to be available at packagist for composer 1.x.

Edit you root `composer.json` file, add the package information to the `repositories` sections:
```json
{
    "type": "package",
    "package": {
        "name": "fivejars/vyva",
        "version": "1.0.0",
        "type": "drupal-module",
        "dist": {
            "url": "https://github.com/fivejars/openy_vyva/archive/1.0.0.zip",
            "type": "zip"
        }
    }
}
```

Now install it with `composer require fivejars/vyva`.

### Module installation

Install as any other drupal module:
1. login as an administrator
2. go to Admin Menu > Extend  	
3. find the "Virtual Y Video Automation", tick the checkbox next to it, click on the "Install" button.
4. verify that the module is installed:
    1. there is Admin menu > Virtual Y > Virtual YMCA settings > Video Automation menu item
    2. it navigates to the form for the Video Automation Configuration
5. configure the module according to the https://github.com/fivejars/vyva/wiki/Drupal-module-installation-and-configuration
