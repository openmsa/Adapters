from setuptools import setup
from setuptools.command.install_egg_info import install_egg_info
from glob import glob
from os import path

ad = "adapters/"
dd = "/opt/sms/bin/php/"

def map_dirs(src, x, y):
    return [ (dd+y.format(d.split(path.sep)[1]), glob(d+"/"+x))
                for d in glob(src+'/*') ]


class null_install_egg_info(install_egg_info):
    def run(self): pass

setup(
    name="openmsa-adapters",
    version="",
    data_files=(
	map_dirs(ad, '*.php', '{}') +
	map_dirs(ad, 'parserd/*.php', 'parserd/filter/{}') +
	map_dirs('parserd/', '*.php', 'parserd/filter/{}') +
	[ (dd+'polld/', glob(ad+'*/polld/*.php')) ] +
	[ (dd+'polld/', glob('polld/*.php')) ]
    ),
    cmdclass={ 'install_egg_info': null_install_egg_info },
)
