Description of import of Horde libraries
# Clone the Horde Git Tools repository and install. You will need
  this for future updates:
    https://github.com/horde/git-tools
# Make sure to follow the #Configuration step mentioned in the URL above. In
  particular make sure to set the 'git_base' config option in conf.php
# Go into the repository cloned above and perform the following:
    bin/horde-git-tools git clone
  (Go for a coffee, this will take a while)
# Checkout the latest stable version for all repos, currently 5.2:
    bin/horde-git-tools git checkout FRAMEWORK_5_2
# Copy the following script and store it on /tmp, change it's execute bit(chmod 777), and run it,
  passing in your path to Horde (the directory you've cloned the repository):
    /tmp/copyhorde.sh ~/git/base/directory/from/step/2


Notes:
* 2022-10-26 added the following PRs/commits for the PHP 8.1 compatibility:
** PR https://github.com/horde/Mime/pull/6
** PR https://github.com/horde/Stream/pull/1
** Horde/Mail is copied from https://github.com/bytestream/Mail/tree/v2.7.1


====
#!/bin/sh

source=$1
target=./lib/horde

echo "Copy Horde modules from $source to $target"

modules="Crypt_Blowfish Exception Idna Imap_Client Mail Mime Secret Socket_Client Stream Stream_Filter Stream_Wrapper Support Text_Flowed Translation Util"

rm -rf $target/locale $target/framework
mkdir -p $target/locale $target/framework/Horde

for module in $modules
do
  echo "Copying $module"
  cp -Rf $source/$module/lib/Horde/* $target/framework/Horde
  locale=$source/$module/locale
  if [ -d $locale ]
  then
    cp -Rf $locale/* $target/locale
  fi
done
