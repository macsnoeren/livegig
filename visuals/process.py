#!/usr/bin/python3

#!/usr/bin/python3

# Used to process the files in the given directory

import json
from os import listdir
from os.path import isfile, join

mypath = "images"
onlyfiles = [f for f in listdir(mypath) if isfile(join(mypath, f))]

print(onlyfiles)

print(json.dumps(onlyfiles, indent=4))
