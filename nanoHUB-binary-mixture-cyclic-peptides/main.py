# ----------------------------------------------------------------------
#  MAIN PROGRAM - generated by the Rappture Builder
# ----------------------------------------------------------------------
import Rappture
import sys
import os
from Creator_lammpsdata import LammpsDataGenerator
from Process_output import Process_output
from Process_COM import Process_COM
from Options import Options
from Prepare_jmol import Prepare_jmol
import Modify_template
from subprocess import Popen, PIPE
from math import *

# open the XML file containing the run parameters
io = Rappture.library(sys.argv[1])

#########################################################
# Get input values from Rappture
#########################################################
options = Options()

# get input value for input.boolean(value37)
# returns value as string "yes" or "no"
options.periodic_ = io.get('input.boolean(value37).current') == 'yes'

options.limitconcentration_ = io.get('input.boolean(value56).current') == 'yes'

options.concentration_ = float(io.get('input.number(value57).current'))

# get input value for input.integer(value38)
options.percentred_ = float(io.get('input.number(value38).current'))

# get input value for input.number(value36)
options.numunits_ = int(io.get('input.integer(value36).current'))

# get input value for input.number(value35) and convert to K
strab = io.get('input.number(value35).current')
options.temp_ = Rappture.Units.convert(strab, to="K", units="off")

# get input value for input.number(value39)
options.redtored_ = float(io.get('input.number(value39).current'))

# get input value for input.number(value40)
options.bluetoblue_ = float(io.get('input.number(value40).current'))

# get input value for input.number(value41)
options.redtoblue_ = float(io.get('input.number(value41).current'))

options.numsides_ = int(io.get('input.integer(value45).current'))

options.time_ = float(io.get('input.number(value84).current'))

# get input value for input.boolean(value87)
# returns value as string "yes" or "no"
options.openjmol_ = io.get('input.boolean(value87).current') == 'yes'

#########################################################
Rappture.Utils.progress(0, "Starting, writing data file")

datafile = "project.data"
obj = LammpsDataGenerator()
obj.CreateFile(datafile)

Rappture.Utils.progress(5, "Writing lammps script")

Lammpstemplate = "in.template"
LammpsScript = "in.binmix"
Lammpstraj = "project.xyz"
f = Modify_template.ModifyLammpsInput(Lammpstemplate, datafile, Lammpstraj)
f.WriteFile(LammpsScript)

Rappture.Utils.progress(10, "Running Lammps simulation")

ThermoInfo = "out.txt"
cmd = "lmp_serial< " + LammpsScript + "> " + ThermoInfo
p = Popen(cmd, shell=True)
p.communicate()

if options.openjmol_:
    traj = Prepare_jmol(Lammpstraj)
    traj.Write_newxyz("projectmod.xyz")
    traj.Write_jmol("jmol.script")
    cmd = ['bash', 'jmol.sh', '-s', 'jmol.script', 'projectmod.xyz']
    p = Popen(cmd, close_fds=True)

Rappture.Utils.progress(90, "Processing output")

com = "com.txt"
input = open(com, 'r')
obj = Process_COM(input)
for kk in range(1,12):
    histo = obj.Execute()
    io.put('output.sequence(lengths).element('+ str(kk) + ').histogram.component.xy', histo, append=1)
input.close()

Rappture.Utils.progress(100, "Done")
#########################################################
# Save output values back to Rappture
#########################################################

io.put('output.number(value49).current', obj.Averagelength())
io.put('output.histogram(interfaces).component.xy', obj.Interfaceshisto(), append=1)

#io.put('output.structure.components.molecule.lammps', "project.lammpstrj", append=0, type="file")
# save output value for output.curve(value43)
# this shows just one (x,y) point -- modify as needed
EngVsTime = Process_output(ThermoInfo, "TotEng", 5)
io.put('output.curve(value43).component.xy', EngVsTime, append=1)

# save output value for output.curve(value44)
# this shows just one (x,y) point -- modify as needed
#line = "%g %g
#" % (x, y)
TempVsTime = Process_output(ThermoInfo, "Temp", 5)
io.put('output.curve(value44).component.xy', TempVsTime, append=1)


Rappture.result(io)

sys.exit()
