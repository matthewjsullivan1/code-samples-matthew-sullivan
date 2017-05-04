import math
import os
from Options import Options

class ModifyLammpsInput:

    def __init__(self, templateinput, Datainput, Trajoutput):
        options = Options()
        self.templateinput_ = templateinput
        self.Datainput_ = Datainput
        self.Traj_ = Trajoutput
        if options.periodic_ == True: self.boundary_ = "p p p"
        else: self.boundary_ = "f f f"
        self.AtoA_ = str(options.redtored_)
        self.AtoB_ = str(options.redtoblue_)
        self.BtoB_ = str(options.bluetoblue_)
        self.Temp_ = str(options.temp_)
        self.Time_ = str(math.trunc(options.time_ * 1000000 / 5.0))
        self.DumpTime_ = str(math.trunc(int(self.Time_) / 10))

    def _WriteTemplate(self):
        template = open(self.templateinput_, 'w')
        template.write("""
# 2-d polymer simulation
    
dimension	3
boundary       $Boundary
units	        real
atom_style	full
        
timestep	5.0
        
read_data  	$Datainput
        
# Bonded interactions
bond_style harmonic
bond_coeff 1 5.975 3.82
bond_coeff 2 5.975 4
        
delete_bonds all multi remove
        
pair_style hybrid lj/cut/coul/cut 10 lj/cut 5.27
pair_coeff 1 1 lj/cut/coul/cut $AtoA 4.7
pair_coeff 1 2 lj/cut 0.5 4.7
pair_coeff 1 3 lj/cut/coul/cut $AtoB 4.7
pair_coeff 2 2 lj/cut 0.5 4.7
pair_coeff 2 3 lj/cut 0.5 4.7
pair_coeff 3 3 lj/cut/coul/cut $BtoB 4.7
        
pair_modify shift yes
        
# Output
thermo		1000
thermo_style 	multi
group           center type 1 3
dump		22 center xyz 1000 $Traj
        
compute         cen all com/molecule
fix             35 all ave/time 1 1 $DumpTime c_cen[1] c_cen[2] c_cen[3] file com.txt mode vector
        
# Brownian dynamics
fix 1 all rigid/nve molecule langevin $Temp $Temp 1500.0 987342
        
run $Time
        """)

    def WriteFile(self, fileoutput):
       self._WriteTemplate()
       input = open(self.templateinput_, 'r')
       output = open(fileoutput, 'w')
       for line in input:
           result = line.replace("$Datainput", self.Datainput_)
           result = result.replace("$Traj", self.Traj_)
           result = result.replace("$Boundary", self.boundary_)
           result = result.replace("$AtoA", self.AtoA_)
           result = result.replace("$AtoB", self.AtoB_)
           result = result.replace("$BtoB", self.BtoB_)
           result = result.replace("$Temp", self.Temp_)
           result = result.replace("$Time", self.Time_)
           result = result.replace("$DumpTime", self.DumpTime_)
           output.write(result)
       input.close()
       output.close()
       os.remove("./" + self.templateinput_)
