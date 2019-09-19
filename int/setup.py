from setuptools import setup

# from setup_utils import map_dirs, flat_dir, deep_dir
# from setup_utils import set_dest, null_install_egg_info

ad = "adapters/"

set_dest('/opt/sms/bin/php/')

setup(
    name="openmsa-adapters",
    version="",
    data_files=(
	map_dirs(ad, '*.php', '{}') +
	map_dirs(ad, '*.xsl', '{}') +
	map_dirs(ad, 'conf/*.conf', '../../templates/devices/{}/conf') +
	map_dirs(ad, 'parserd/*.php', 'parserd/filter/{}') +
	map_dirs('parserd/', '*.php', 'parserd/filter/{}') +
	flat_dir(ad, '*/polld/*.php', 'polld') +
	flat_dir('polld/', '*.php', 'polld') +
	deep_dir('vendor')
    ),
    cmdclass={ 'install_egg_info': null_install_egg_info },
)
