app_name=lookup-server

build_dir=$(CURDIR)/build/artifacts
appstore_dir=$(build_dir)/appstore
source_dir=$(build_dir)/source
sign_dir=$(build_dir)/sign
package_name=$(shell echo $(app_name) | tr '[:upper:]' '[:lower:]')
cert_dir=$(HOME)/.nextcloud/certificates
branch=master
version=1.1.0

all: release

clean:
	rm -rf $(build_dir)

clean-composer:
	rm -fr server/vendor/

composer:
	composer install -d server --no-dev

release: clean clean-composer composer
	mkdir -p $(sign_dir)
	rsync -a \
	--exclude=/build \
	--exclude=/docs \
	--exclude=/translationfiles \
	--exclude=/.tx \
	--exclude=/tests \
	--exclude=.git \
	--exclude=/.github \
	--exclude=/l10n/l10n.pl \
	--exclude=/CONTRIBUTING.md \
	--exclude=/issue_template.md \
	--exclude=/README.md \
	--exclude=/composer.json \
	--exclude=/testConfiguration.json \
	--exclude=node_modules \
	--exclude=/composer.lock \
	--exclude=/.gitattributes \
	--exclude=/.gitignore \
	--exclude=/.scrutinizer.yml \
	--exclude=/.travis.yml \
	--exclude=/Makefile \
	./ $(sign_dir)/$(package_name)
	tar -czf $(build_dir)/$(package_name)-$(version).tar.gz \
		-C $(sign_dir) $(package_name)
