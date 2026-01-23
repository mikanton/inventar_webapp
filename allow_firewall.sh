#!/bin/bash
echo "Configuring Firewall for Mobile Access..."
echo "This requires sudo privileges."

# Add node to firewall exception
NODE_PATH=$(which node)
sudo /usr/libexec/ApplicationFirewall/socketfilterfw --add $NODE_PATH
sudo /usr/libexec/ApplicationFirewall/socketfilterfw --unblockapp $NODE_PATH

echo "---------------------------------------------------"
echo "Firewall configured!"
echo "If you still have issues, try turning off the firewall temporarily:"
echo "System Settings -> Network -> Firewall"
echo "---------------------------------------------------"
