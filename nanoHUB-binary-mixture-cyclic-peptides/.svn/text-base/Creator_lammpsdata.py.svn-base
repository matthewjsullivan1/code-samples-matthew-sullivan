import os,sys,string 
import time
import random
import math
import numpy as np
import random
import time
from Randptswithcutoff import Randptswithcutoff
from Options import Options

#-------------------------------------------------------------------------------
#	Script to generate binary mixture of CPNs for LAMMPS
#------------------------------------------------------------------------------
# Instructions at bottom of file

class LammpsDataGenerator:

    # Define the input variables
    # numberunits - number of polygons created
    # percentageA - percent(100>=x>=0) of polygon containing type 1 atoms
    # numsides - number of sides of the regular polygon (x>=3)
    # concen - concentration(optional) and has units subunits/(angstrom^3)
    # type 1 atoms - A subunit
    # type 2 atoms - Shielding beeds found on both subunits
    # type 3 atoms - B subunit
    def __init__(self):
        options = Options()
        self.numunits_ = options.numunits_
        self.percentageA_ = options.percentred_
        self.numsides_ = options.numsides_
        if(options.limitconcentration_):
            self.concen_ = options.concentration_
            boxlength_ = (self.numunits_ / self.concen_) ** (1/3.0)
            self.box_ = [0, boxlength_, 0, boxlength_, 0, boxlength_]
        else:
            self.box_ = [0, 50, 0, 50, 0, 50]
        options.box_ = self.box_
        self.bondl1_ = 3.82 #side length of polygon
        self.bondl2_ = 4.0 #distance node extends from polygon
        self.subunitcenters_ = []
        self.unittype_ = []
        self.bondpairs_ = []
        self.atominfo_ = []
        self.mass_ = [50., 50., 50.]
        #self.neighbors_ = [[ii,jj,kk] for ii in [-2,-1,0,1,2] 
        #                   for jj in [-2,-1,0,1,2] for kk in [-2,-1,0,1,2] 
        #                   if math.fabs(ii*jj*kk) != 8]
        self.neighbors_ = [[ii,jj,kk] for ii in [-1,0,1] for jj in [-1,0,1] 
                                                         for kk in [-1,0,1]]
        self.neighbors_.extend([ii,jj,kk] for ii in [-1,0,1] for jj in [-1,0,1] 
                                                             for kk in [-2,2])
        self.neighbors_.extend([ii,jj,kk] for ii in [-1,0,1] for jj in [-2,2] 
                                                             for kk in [-1,0,1])
        self.neighbors_.extend([ii,jj,kk] for ii in [-2,2] for jj in [-1,0,1] 
                                                           for kk in [-1,0,1])

        

#-------------------------------------------------------------------------------

    def _Buildintro(self):  
         natypes_ = 3
         nbondtypes_ = 2
         nangletypes_  = 0
         ndihtypes_ = 0
         nimprtypes_ = 0
         ndihedrals_ = 0
         natoms_ = self.numunits_ * self.numsides_ * 2
         nbonds_ = self.numunits_ * self.numsides_ * 2
         nangles_ = 0
         nimpropers_ = 0 
         mass_ = self.mass_
         Intro_ = []
         Intro_.append("LAMMPS data file\n\n")
         Intro_.append("%d atoms\n" % natoms_)
         Intro_.append("%d bonds\n" % nbonds_)
         Intro_.append("%d angles\n" % nangles_)
         Intro_.append("%d dihedrals\n" % ndihedrals_)
         Intro_.append("%d impropers\n\n" % nimpropers_)

         Intro_.append("%d atom types\n" % natypes_)
         Intro_.append("%d bond types\n" % nbondtypes_)
         Intro_.append("%d angle types\n" % nangletypes_)

         Intro_.append("%d improper types\n\n" % nimprtypes_)

         Intro_.append("%f %f xlo xhi\n" % (self.box_[0], self.box_[1]))
         Intro_.append("%f %f ylo yhi\n" % (self.box_[2], self.box_[3]))
         Intro_.append("%f %f zlo zhi\n\n" % (self.box_[4], self.box_[5]))

         Intro_.append("Masses\n\n")

         for ii in range(len( mass_ )):
             Intro_.append("%d %f \n" % (ii+1, mass_[ii]))
	
         Intro_.append ("\n")
         return Intro_

    def _Buildcenters(self):

        def Calcxyzcoord(self):
            theta_ = 2 * math.pi / self.numsides_
            angl_ = (math.pi - theta_ ) / 2
            length_ = self.bondl1_ / (2 * math.cos(angl_))
            cutoff_ = 2.2 * (length_ + self.bondl2_)
            obj = Randptswithcutoff(self.numunits_, cutoff_, self.box_)
            self.subunitcenters_ = obj.Calcpts()
                        
        def Calcunittype(self):
            #Label all units one type
            self.unittype_ = [3 for __ in range(1, self.numunits_ + 1)]
            #Based on the input, create the correct number of opposite units 
            #that are randomally selected
            numA_ = math.trunc(self.numunits_ * self.percentageA_ / 100)
            randlist_ = random.sample(xrange(self.numunits_), numA_) 
            for ll in randlist_:
                self.unittype_[ll] = 1
        
        Calcxyzcoord(self)
        Calcunittype(self)
        Options().unittype_ = self.unittype_

    def _Randrotmatrix(self, angl1, angl2, angl3):
        #angles should be randomally distributed from [0, 1]
        v_ = np.array(([math.cos(2 * math.pi * angl2) * math.sqrt(angl3)],
                     [math.sin(2 * math.pi * angl2) * math.sqrt(angl3)],
                     [math.sqrt(1. - angl3)]))
       
        H_ = np.identity(3) - 2 * np.dot(v_, v_.transpose())
        R_ = np.array(([math.cos(2*math.pi*angl1), math.sin(2*math.pi*angl1), 0.],
                     [-math.sin(2*math.pi*angl1), math.cos(2*math.pi*angl1), 0.],
                     [0., 0., 1.]))
        return -np.dot(H_, R_)

    def _PolygonFactory(self):
        theta_ = 2 * math.pi / self.numsides_
        angl_ = (math.pi - theta_ ) / 2
        length_ = self.bondl1_ / (2 * math.cos(angl_))
        PolyCoords_ = np.zeros((3, self.numsides_))
        NodeCoords_ = np.zeros((3, self.numsides_))
        Pairs_ = []
        for ii in range(self.numsides_):
            PolyCoords_[0, ii] = length_ * math.cos(ii * theta_) + 0.
            PolyCoords_[1, ii] = length_ * math.sin(ii * theta_) + 0.
            PolyCoords_[2, ii] = 0.
            NodeCoords_[0, ii] = ((length_ + self.bondl2_) 
                                 * math.cos(ii * theta_) + 0.)
            NodeCoords_[1, ii] = ((length_ + self.bondl2_) 
                                 * math.sin(ii * theta_) + 0.)
            NodeCoords_[2, ii] = 0.

            Pairs_.append("%d %d %d" % (2, ii + 1, ii + self.numsides_ + 1))
            if ii != self.numsides_ -1: 
                Pairs_.append("%d %d %d" % (1, ii + 1, ii + 2)) 
                #connect adj. atoms of polygon
            else:                
                Pairs_.append("%d %d %d" % (1, 1, ii + 1)) 
                #connect first to last of polygon
 
        return PolyCoords_, NodeCoords_, Pairs_

    def _WritePolygons(self, polycoords, nodecoords, pairs):
        index_ = 0
        for ii in range(self.numunits_):
            RotMatrix = self._Randrotmatrix(random.random(), random.random(), 
                                                            random.random())
            Modpolycoords = np.dot(RotMatrix, polycoords)
            Modnodecoords = np.dot(RotMatrix, nodecoords)
            centerx = self.subunitcenters_[ii][0]
            centery = self.subunitcenters_[ii][1]
            centerz = self.subunitcenters_[ii][2]
            for jj in range(Modpolycoords.shape[1]):
                index_ += 1 
                self.atominfo_.append("%d %d %d %d %f %f %f\n" % (index_, ii+ 1,
                        self.unittype_[ii], 0., Modpolycoords[0, jj] + centerx, 
                                                Modpolycoords[1, jj] + centery, 
                                                Modpolycoords[2, jj] + centerz))
            for kk in range(Modnodecoords.shape[1]):
                index_ += 1
                self.atominfo_.append("%d %d %d %d %f %f %f\n" % (index_, ii+1,
                                         2, 0., Modnodecoords[0, kk] + centerx, 
                                                Modnodecoords[1, kk] + centery, 
                                                Modnodecoords[2, kk] + centerz))
            for line in pairs:
                line = line.split()    
                self.bondpairs_.append("%s %d %d\n" % (line[0], int(line[1]) 
                                        + ii * self.numsides_ * 2,  
                                        int(line[2]) + ii * self.numsides_ * 2))

    def _Buildatoms(self):
        self._Buildcenters()
        self._WritePolygons(*self._PolygonFactory())
        Atoms_ = []
	Atoms_.append ("Atoms\n\n")
	Atoms_.extend(self.atominfo_)        
	Atoms_.append("\n")
	return Atoms_ 

    def _Buildbonds(self):
        Bonds_ = []
        Bonds_.append("Bonds\n\n")
        for ii in range(len(self.bondpairs_)):
            Bonds_.append("%d %s" % (ii+1, self.bondpairs_[ii]))
        Bonds_.append("\n")
        return Bonds_

    def CreateFile(self, filename = "project.data"):   
        while True:
            try:
                total = self._Buildintro() + self._Buildatoms() +self._Buildbonds()
                break
            except ValueError as error:
                if not Options().limitconcentration_:
                    error = str(error)
                    error = error[1:len(error)-1].split() 
                    if error[0] == "Percentfilled":
                        percfilled = float(error[1])
                        curvol = ( (self.box_[1]-self.box_[0]) 
                                 * (self.box_[3]-self.box_[2])
                                 * (self.box_[5]-self.box_[4]))
                        newvol = 1.05 * curvol / percfilled
                        newboxlength = newvol**(1.0/3.0)
                        self.box_ = [0, newboxlength, 0, newboxlength, 
                                                      0, newboxlength]
                    else: 
                        self.box_ = [0, self.box_[1] + 10, 
                                     0, self.box_[3] + 10, 
                                     0, self.box_[5] + 10]
                    Options().box_ = self.box_
                else:
                    raise 
        f = open(filename,'w')
        f.writelines(total)
        f.close()

#For testing: 
#from Options import Options
#options = Options()
#options.numunits_ = 50
#options.numsides_ = 6
#options.percentred_ = 50
#options.limitconcentration_ = False
#options.concentration_ = 0
#data = LammpsDataGenerator()
#data.CreateFile("Test3.data")
