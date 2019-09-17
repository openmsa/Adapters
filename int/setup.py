from setuptools import setup
from setuptools.command.install_egg_info import install_egg_info
from glob import glob

ad = "adapters/"

TOPDIRS_FILES = glob(ad+'*/*.php')
PARSERD_FILES = glob(ad+'*/parserd/*.php')
POLLD_FILES = glob(ad+'*/polld/*.php')


def topdir(x): return x.split('/')[1]

top_dirs = { topdir(f) : 0 for f in TOPDIRS_FILES }

class null_install_egg_info(install_egg_info):
    def run(self): pass

setup(
    name="openmsa-adapters",
    version="",
    data_files=[
	('/opt/sms/bin/php/'+d, glob(ad+d+"/*.php")) for d in top_dirs
    ] + [
	('/opt/sms/bin/php/parserd/filter', PARSERD_FILES),
	('/opt/sms/bin/php/polld/', POLLD_FILES),
    ],
    cmdclass={ 'install_egg_info': null_install_egg_info },
)
