import os,sys,string 
import time
import random
import math
import numpy as np
import random
from Options import Options

#-------------------------------------------------------------------------------
#	Script to generate binary mixture of CPNs for LAMMPS
#------------------------------------------------------------------------------
# Instructions at bottom of file

class LammpsDataGenerator:

    # Define the input variables
    # numberunits - number of polygons created, even distribution if perfect cube
    # percentageA - percent(100>=x>=0) of polygon containing type 1 atoms
    # numsides - number of sides of the regular polygon (x>=3)
    # concen - concentration(optional) and has units subunits/(angstrom^3)
    # type 1 atoms - A subunit
    # type 2 atoms - Shielding beeds found on both subunits
    # type 3 atoms - B subunit
    def __init__(self, numberunits, percentageA, numsides, concen=-1):
        self.numunits_ = numberunits
        self.percentageA_ = percentageA
        self.numsides_ = numsides
        self.concen_ = concen
        if(concen >= 0):
            boxlength_ = (numberunits / concen) ** (1/3.0)
            self.xmax_ = boxlength_
            self.ymax_ = boxlength_ 
            self.zmax_ = boxlength_
        else:
            self.xmax_ = 50
            self.ymax_ = 50
            self.zmax_ = 50
        self.bondl1_ = 3.82 #side length of polygon
        self.bondl2_ = 4.0 #distance node extends from polygon
        self.subunitcenters_ = []
        self.unittype_ = []
        self.bondpairs_ = []
        self.atominfo_ = []
        self.mass_ = [50., 50., 50.]
        self.neighbors_ = [[ii,jj,kk] for ii in [-1,0,1] for jj in [-1,0,1] for kk in [-1,0,1]]
        self.neighbors_.extend([ii,jj,kk] for ii in [-1,0,1] for jj in [-1,0,1] for kk in [-2,2])
        self.neighbors_.extend([ii,jj,kk] for ii in [-1,0,1] for jj in [-2,2] for kk in [-1,0,1])
        self.neighbors_.extend([ii,jj,kk] for ii in [-2,2] for jj in [-1,0,1] for kk in [-1,0,1])

        self.options_ = Options()
        self.options_.box = [0, self.xmax_, 0, self.ymax_, 0, self.zmax_]
        

#-------------------------------------------------------------------------------

    def Buildintro(self):  
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

         Intro_.append("%f %f xlo xhi\n" % (0.00, self.xmax_))
         Intro_.append("%f %f ylo yhi\n" % (0.00, self.ymax_))
         Intro_.append("%f %f zlo zhi\n\n" % (0.00, self.zmax_))

         Intro_.append("Masses\n\n")

         for ii in range(len( mass_ )):
             Intro_.append("%d %f \n" % (ii+1, mass_[ii]))
	
         Intro_.append ("\n")
         return Intro_

    def Buildcenters(self):

        def MinimDistBetweenSubunits(self):
            #Dependent on regular polygan subunit
            theta_ = 2 * math.pi / self.numsides_
            angl_ = (math.pi - theta_ ) / 2
            length_ = self.bondl1_ / (2 * math.cos(angl_))
            return 2.2 * (length_ + self.bondl2_)
        
        def Maxsubunits(self, sphererad):
            packingfactor = .64
            spherevol = (4/3) * math.pi * sphererad**3
            boxvol = self.xmax_ * self.ymax_ * self.zmax_
            return math.floor(cellvol / spherevol * packingfactor)
        
        def Randposincube(cubecorner, cubedim):
            randpos_ = [0, 0, 0]
            randpos_[0] = random.random() * cubedim[0] + cubecorner[0]
            randpos_[1] = random.random() * cubedim[1] + cubecorner[1]
            randpos_[2] = random.random() * cubedim[2] + cubecorner[2]
            return randpos_

        def Nooverlap(pos, cubeindex, boxsize, coordsincubes, minim):
            def Tooclose(pos1, pos2, cutoff):
                width = [self.options_.box[1] - self.options_.box[0],
                         self.options_.box[3] - self.options_.box[2],
                         self.options_.box[5] - self.options_.box[4]]
                incr = [math.fabs(pos1[0] - pos2[0]),
                        math.fabs(pos1[1] - pos2[1]),
                        math.fabs(pos1[2] - pos2[2])]
                for ii in range(len(incr)):
                    if incr[ii] > 0.5 * width[ii]:
                        incr[ii] = width[ii] - incr[ii]
                dist = math.sqrt(incr[0]**2 + incr[1]**2 +incr[2]**2) 
                return dist < cutoff 

            def Neighbors(cubeindex, boxsize):
                cubeindex = map(int, cubeindex.split())
                cubeneigh_ = [[cubeindex[0] + ii[0],
                               cubeindex[1] + ii[1],
                               cubeindex[2] + ii[2]] for ii in self.neighbors_]
                cubeneigh_ = [[ii[0] % boxsize[0],
                               ii[1] % boxsize[1],
                               ii[2] % boxsize[2]] for ii in cubeneigh_]
                return ["%d %d %d" % (ii[0], ii[1], ii[2]) for ii in cubeneigh_ if ii != cubeindex]
             
            listneighbors = Neighbors(cubeindex, boxsize)
            for index in listneighbors:
                hassubunit = index in coordsincubes
                if hassubunit and Tooclose(pos, coordsincubes[index], minim):
                    return False
            return True

        def Calcxyzcoord(self):
            minimdist_ = MinimDistBetweenSubunits(self) 
            if self.numunits_ >= MaxSubunits(self, Minimdist_ / 2):
                raise self.AtomOverlap("Impossible to pack this dense")
            #Divide box into cubes
            boxsize_ = [0, 0, 0]
            optimal_ = minimdist_ / math.sqrt(2)
            boxsize_[0] = math.floor((self.xmax_) / optimal_)
            boxsize_[1] = math.floor((self.ymax_) / optimal_) 
            boxsize_[2] = math.floor((self.zmax_) / optimal_)
            numcubes_ = boxsize_[0] * boxsize_[1] * boxsize_[2]
            if numcubes_ == 0:
                raise self.AtomOverlap("At least one box dimension too narrow")
            cubedim_ = [0, 0, 0]
            cubedim_[0] = (self.xmax_) / cubesize[0] 
            cubedim_[1] = (self.ymax_) / cubesize[1] 
            cubedim_[2] = (self.zmax_) / cubesize[2] 
            cubecoords_ = []
            prismxsection_ = boxsize_[0] * boxsize_[1]
            #cubes coords from lower left vertex
            for ii in range(numcubes_):
                colnum_ = math.trunc((ii % prismxsection_) / boxsize_[1])
                rownum_ = (ii % prismxsection_) % boxsize_[1]
                heighnum_ = math.floor(ii / prismxsection_)
                index = "%d %d %d" % (colnum_, rownum_, heighnum_) 
                cubecoords_[index] = ([colnum_ * cubedim_[0], 
                                       rownum_ * cubedim_[1],
                                       heighnum_ * cubedim_[2]])
            emptycubes_ = set(cubecoords_.keys())
            coordsincubes_ = dict()
            for jj in range(self.numunits_):
                itrcutoff = len(emptycubes_ * 3)
                while itrcutoff > 0:
                    randcubeindex_ = random.sample(emptycubes_, 1)
                    itr = 10
                    while itr > 0:
                        randpos_ = Randposincube(cubecoords_[randcubeindex_], 
                                                                      cubedim_)
                        if Nooverlap(randpos_, randcubeindex_, boxsize_, 
                                                        coordsincubes_, minim):
                            self.subunitcenters_.append(randpos_)
                            coordsincubes_[randcubeindex_] = randpos_                     
                            emptycubes_.remove(randcubeindex_)
                            break
                        else:
                            itr = itr - 1
                    if itr == 0:
                        itrcutoff = itrcutfoff -1  
                    else:
                        break
                if itrcutoff == 0:
                    raise self.AtomOverlap("Boxsize must be increased")
 
        """def Calcxyzcoord(self):
            self.xcoord_ = []
            self.ycoord_ = []
            self.zcoord_ = []
            #Produce x,y,z coordinates of evenly spaced grid of atoms
            if self.cubelength_**3 != self.numunits_:
                set_ = random.sample(xrange(self.cubelength_**3), self.numunits_) 
                set_.sort()
            else:
                set_ = range(self.numunits_)
            sqarea_ = self.cubelength_**2
            for ii in set_:
                colnum_ = math.trunc((ii % sqarea_) / self.cubelength_)
                rownum_ = (ii % sqarea_) % self.cubelength_
                depnum_ = math.floor(ii / sqarea_)
                self.xcoord_.append(colnum_ * self.sx_ + self.sx_ / 2)
                self.ycoord_.append(rownum_ * self.sy_ + self.sy_ / 2)
                self.zcoord_.append(depnum_ * self.sz_ + self.sz_ / 2)"""

        def Calcunittype(self):
            #Label all units one type
            self.unittype_ = [3 for __ in range(1, self.numunits_ + 1)]
            #Based on the input, create the correct number of opposite type units 
            #that are randomally distributed throughout grid
            numA_ = math.trunc(self.numunits_ * self.percentageA_ / 100)
            randlist_ = random.sample(xrange(self.numunits_), numA_) 
            for ll in randlist_:
                self.unittype_[ll] = 1
        
        Calcxyzcoord(self)
        Calcunittype(self)
        self.options_.unittype = self.unittype_
 
    class AtomOverlap(Exception):
        def __init__(self, value):
            self.value = value
        def __str__(self):
            return repr(self.value)

    def RandRotMatrix(self, angl1, angl2, angl3):
        #angles should be randomally distributed from [0, 1]
        v_ = np.array(([math.cos(2 * math.pi * angl2) * math.sqrt(angl3)],
                     [math.sin(2 * math.pi * angl2) * math.sqrt(angl3)],
                     [math.sqrt(1. - angl3)]))
       
        H_ = np.identity(3) - 2 * np.dot(v_, v_.transpose())
        R_ = np.array(([math.cos(2*math.pi*angl1), math.sin(2*math.pi*angl1), 0.],
                     [-math.sin(2*math.pi*angl1), math.cos(2*math.pi*angl1), 0.],
                     [0., 0., 1.]))
        return -np.dot(H_, R_)

    def PolygonFactory(self):
        theta_ = 2 * math.pi / self.numsides_
        angl_ = (math.pi - theta_ ) / 2
        length_ = self.bondl1_ / (2 * math.cos(angl_))
        PolyCoords_ = np.zeros((3, self.numsides_))
        NodeCoords_ = np.zeros((3, self.numsides_))
        Pairs_ = []
        if self.sx_ <= 2.5 * (length_ + self.bondl2_):
            raise self.AtomOverlap("Boxsize must be increased")
        for ii in range(self.numsides_):
            PolyCoords_[0, ii] = length_ * math.cos(ii * theta_) + 0.
            PolyCoords_[1, ii] = length_ * math.sin(ii * theta_) + 0.
            PolyCoords_[2, ii] = 0.
            NodeCoords_[0, ii] = (length_ + self.bondl2_) * math.cos(ii * theta_) + 0.
            NodeCoords_[1, ii] = (length_ + self.bondl2_) * math.sin(ii * theta_) + 0.
            NodeCoords_[2, ii] = 0.

            Pairs_.append("%d %d %d" % (2, ii + 1, ii + self.numsides_ + 1))
            if ii != self.numsides_ -1: 
                Pairs_.append("%d %d %d" % (1, ii + 1, ii + 2)) #connet adj. atoms of polygon
            else:                
                Pairs_.append("%d %d %d" % (1, 1, ii + 1)) #connect first to last of polygon
 
        return PolyCoords_, NodeCoords_, Pairs_

    def WritePolygons(self, polycoords, nodecoords, pairs):
        index_ = 0
        for ii in range(self.numunits_):
            RotMatrix = self.RandRotMatrix(random.random(), random.random(), 
                                                            random.random())
            Modpolycoords = np.dot(RotMatrix, polycoords)
            Modnodecoords = np.dot(RotMatrix, nodecoords)
            centerx = self.subunitcenters_[ii][0]
            centery = self.subunitcenters_[ii][1]
            centerz = self.subunitcenters_[ii][2]
            for jj in range(Modpolycoords.shape[1]):
                index_ += 1 
                self.atominfo_.append("%d %d %d %d %f %f %f\n" % (index_, ii + 1,
                                  self.unittype_[ii], 0., Modpolycoords[0, jj] + centerx, 
                                                          Modpolycoords[1, jj] + centery, 
                                                          Modpolycoords[2, jj] + centerz))
            for kk in range(Modnodecoords.shape[1]):
                index_ += 1
                self.atominfo_.append("%d %d %d %d %f %f %f\n" % (index_, ii + 1,
                                           2, 0., Modnodecoords[0, kk] + centerx, 
                                                  Modnodecoords[1, kk] + centery, 
                                                  Modnodecoords[2, kk] + centerz))
            for line in pairs:
                line = line.split()    
                self.bondpairs_.append("%s %d %d\n" % (line[0], int(line[1]) + ii * self.numsides_ * 2, 
                                                           int(line[2]) + ii * self.numsides_ * 2))

    def Buildatoms(self):
        self.Buildcenters()
        self.WritePolygons(*self.PolygonFactory())
        Atoms_ = []
	Atoms_.append ("Atoms\n\n")
	Atoms_.extend(self.atominfo_)        
	Atoms_.append("\n")
	return Atoms_ 

    def Buildbonds(self):
        Bonds_ = []
        Bonds_.append("Bonds\n\n")
        for ii in range(len(self.bondpairs_)):
            Bonds_.append("%d %s" % (ii+1, self.bondpairs_[ii]))
        Bonds_.append("\n")
        return Bonds_

    def CreateFile(self, filename = "project.data"):   
        while True:
            try:
                total = self.Buildintro() + self.Buildatoms() + self.Buildbonds()
                break
            except self.AtomOverlap:
                if(self.concen_ < 0):
                    self.xmax_ = 20 + self.xmax_ 
                    self.ymax_ = 20 + self.ymax_ 
                    self.zmax_ = 20 + self.zmax_
                    self.sx_ = self.xmax_ / self.cubelength_
                    self.sy_ = self.ymax_ / self.cubelength_
                    self.sz_ = self.zmax_ / self.cubelength_
                    Options.box_ = [0, self.xmax_, 0, self.ymax_, 0, self.zmax_]
                else:
                    raise self.AtomOverlap("Concentration too high")

        f = open(filename,'w')
        f.writelines(total)
        f.close()
        return self.options_

#to run by command line -
#python Creator_lammpsdata.py filename numunits percentageA numofpolygonsides (concen)
#ex. - python Creator_lammpsdata.py Test.data 27 50 8
#Or include no command line arguments and specify below
#if len(sys.argv) == 6:
#    data = LammpsDataGenerator(int(sys.argv[2]),float(sys.argv[3]),int(sys.argv[4]),
#                                                                 float(sys.argv[5]))
#    data.CreateFile(sys.argv[1])
#else: 
data = LammpsDataGenerator(27,50,8)
data.CreateFile("Test3.data")

