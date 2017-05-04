from numpy import *
import math 
from Options import Options

class Process_COM:

    def __init__(self, input):
        options = Options()
        self.input_ = input 
        self.nummol_ = options.numunits_   
        self.xcoord_ = []
        self.ycoord_ = [] 
        self.zcoord_ = []
        self.adjmatrix_ = zeros((self.nummol_,self.nummol_))
        self.cutoff_ = 7.53
        self.lengthlist_ = []
        self.options_ = options

    def _Readcoords(self):
        self.xcoord_ = []
        self.ycoord_ = []
        self.zcoord_ = []
        line = self.input_.readline().split()
        cond = str(self.nummol_) not in line
	while cond:
            line = self.input_.readline().split()
            cond = str(self.nummol_) not in line

        for ii in range(self.nummol_):
            line = self.input_.readline().split()
            self.xcoord_.append(float(line[1]))
            self.ycoord_.append(float(line[2]))
            self.zcoord_.append(float(line[3]))
               
    def AreBonded(self, ind1, ind2):
        if ind1 == ind2: 
            return False
        else:
            coor1_ = [self.xcoord_[ind1], self.ycoord_[ind1], self.zcoord_[ind1]] 
            coor2_ = [self.xcoord_[ind2], self.ycoord_[ind2], self.zcoord_[ind2]]
            width = [self.options_.box_[1] - self.options_.box_[0],
                     self.options_.box_[3] - self.options_.box_[2],
                     self.options_.box_[5] - self.options_.box_[4]]
            incr = [math.fabs(coor1_[0] - coor2_[0]),
                    math.fabs(coor1_[1] - coor2_[1]),
                    math.fabs(coor1_[2] - coor2_[2])]
            for ii in range(len(incr)):
                if incr[ii] > 0.5 * width[ii]:
                    incr[ii] = width[ii] - incr[ii]
            dist = math.sqrt(incr[0]**2 + incr[1]**2 +incr[2]**2) 
            return dist <= self.cutoff_ 

    def _Filladjmatrix(self):
        for ii in range(self.nummol_):
            for jj in range(self.nummol_):
                self.adjmatrix_[ii, jj] = self.AreBonded(ii,jj)    

    def Neighbors(self, ID):
        set = []
        for ii in range(self.nummol_):
            if ii != ID and self.adjmatrix_[ID][ii]:
                set.append(ii)
        return set
      
    def Complength(self, ID, countedlist, length):
        try:
            countedlist.index(ID) 
            NotInvestigated = False
        except ValueError:
            NotInvestigated = True
        if NotInvestigated:
            Bondedmols = self.Neighbors(ID)
            countedlist.append(ID)
            length += 1
            for mols in Bondedmols:
                countedlist, length = self.Complength(mols, countedlist, 
                                                                  length)
            return countedlist, length
        else:
            return countedlist, length
    
    def _Createlengthlist(self):
        countedlist = []
        self.lengthlist_ = []
        for ii in range(self.nummol_):
            countedlist, length = self.Complength(ii, countedlist, 0)
            if length != 0:
                self.lengthlist_.append(length)

    def _Lengthhisto(self):
        Histo = [0, 0, 0, 0, 0]
        for ii in self.lengthlist_:
            if ii > 0 and ii <= 2: Histo[0] += 1
            elif ii > 2 and ii <= 4: Histo [1] += 1
            elif ii > 4 and ii <= 6: Histo [2] += 1
            elif ii > 6 and ii <= 8: Histo [3] += 1
            elif ii > 8: Histo[4] += 1 
        output = []
        categ = ["1-2", "3-4", "5-6", "7-8", "8- "]
        for jj in range(len(Histo)):
            output.append("%s %d" % (categ[jj], Histo[jj])) 
        return "\n".join(output)
  
    def Execute(self):
        self._Readcoords()  
        self._Filladjmatrix()
        self._Createlengthlist()
        return self._Lengthhisto()

    def Interfaceshisto(self):
        categ = ["RedtoRed", "RedtoBlue", "BluetoBlue"]
        Histo = [0, 0, 0] 
        unittype = self.options_.unittype_
        for ii in range(self.adjmatrix_.shape[0]):
            for jj in range(self.adjmatrix_.shape[1]):
                if self.adjmatrix_[ii, jj] == 1 and ii > jj:
                    if unittype[ii] == 1 and unittype[jj] == 1:
                        Histo[0] += 1
                    elif unittype[ii] != unittype[jj]:
                        Histo[1] += 1
                    elif unittype[ii] == 3 and unittype[jj] == 3:
                        Histo[2] += 1
        output = []
        for jj in range(len(Histo)):
            output.append("%s %d" % (categ[jj], Histo[jj])) 
        return "\n".join(output)


    def Averagelength(self):
        sum = 0.0
        count = 0.0
        for ii in self.lengthlist_:
            sum += ii
            count += 1
        if count == 0:
            return 1
        else:
            return (sum / count)

#input = open("com.txt", 'r')
#opt = Options()
#opt.box = [0, 50, 0, 50, 0, 50]
#obj = ProcessCOM(input, 10, opt)
#for ii in range(11):
#    obj.Execute()
#    print obj.xcoord_
#    print obj.ycoord_
#    print obj.zcoord_
#    print obj.lengthlist_
#    print obj.options_.box_
#    print obj.AverageLength()
#    print obj.adjmatrix_
#input.close()

