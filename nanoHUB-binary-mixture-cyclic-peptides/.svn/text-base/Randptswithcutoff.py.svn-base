import math
import random

class Randptswithcutoff:

    def __init__(self, numpts, cutoff, box):
        self.numpts_ = numpts
        self.cutoff_ = cutoff
        self.box_ = box
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
    def _Maxsubunits(self):
        packingfactor = .50
        sphererad = self.cutoff_ / 2
        spherevol = (4/3) * math.pi * sphererad**3
        boxvol = ((self.box_[1]-self.box_[0]) * (self.box_[3]-self.box_[2])
                                              * (self.box_[5]-self.box_[4]))
        return math.trunc(boxvol / spherevol * packingfactor)
    
    def _Randposincube(self, cubeindex, cubedim):
        randpos_ = [0, 0, 0]
        cubeindex = map(int, cubeindex.split())
        randpos_[0] = ((cubeindex[0] + random.random()) * cubedim[0]
                                                     + self.box_[0])
        randpos_[1] = ((cubeindex[1] + random.random()) * cubedim[1]
                                                     + self.box_[2])
        randpos_[2] = ((cubeindex[2] + random.random()) * cubedim[2]
                                                     + self.box_[4])
        return randpos_

    def _Nooverlap(self, pos, cubeindex, boxsize, coordsincubes):
        def Withincutoff(self, pos1, pos2):
            width = [self.box_[1] - self.box_[0],
                     self.box_[3] - self.box_[2],
                     self.box_[5] - self.box_[4]]
            incr = [math.fabs(pos1[0] - pos2[0]),
                    math.fabs(pos1[1] - pos2[1]),
                    math.fabs(pos1[2] - pos2[2])]
            for ii in range(len(incr)):
                if incr[ii] > 0.5 * width[ii]:
                    incr[ii] = width[ii] - incr[ii]
            dist = math.sqrt(incr[0]**2 + incr[1]**2 +incr[2]**2) 
            return dist < self.cutoff_

        def Neighbors(self, cubeindex, boxsize):
            cubeindex = map(int, cubeindex.split())
            cubeneigh_ = [[cubeindex[0] + ii[0],
                           cubeindex[1] + ii[1],
                           cubeindex[2] + ii[2]] for ii in self.neighbors_]
            cubeneigh_ = [[ii[0] % boxsize[0],
                           ii[1] % boxsize[1],
                           ii[2] % boxsize[2]] for ii in cubeneigh_]
            return ["%d %d %d" % tuple(ii) for ii in cubeneigh_ 
                                            if ii != cubeindex]
         
        listneighbors = Neighbors(self, cubeindex, boxsize)
        for index in listneighbors:
            notempty = index in coordsincubes
            if notempty and Withincutoff(self, pos, coordsincubes[index]):
                return False
        return True

    def Calcpts(self):
        ptscenters_ = []
        if self.numpts_ >= self._Maxsubunits():
            raise ValueError("Impossible to pack this dense")
        #Divide box into cubes
        boxsize_ = [0, 0, 0]
        optimal_ = self.cutoff_ / math.sqrt(2)
        boxsize_[0] = math.floor((self.box_[1]-self.box_[0]) / optimal_)
        boxsize_[1] = math.floor((self.box_[3]-self.box_[2]) / optimal_) 
        boxsize_[2] = math.floor((self.box_[5]-self.box_[4]) / optimal_)
        numcubes_ = int(boxsize_[0] * boxsize_[1] * boxsize_[2])
        if numcubes_ == 0:
            raise ValueError("At least one box dimension too narrow")
        cubedim_ = [0, 0, 0]
        cubedim_[0] = (self.box_[1]-self.box_[0]) / boxsize_[0] 
        cubedim_[1] = (self.box_[3]-self.box_[2]) / boxsize_[1] 
        cubedim_[2] = (self.box_[5]-self.box_[4]) / boxsize_[2] 
        emptycubes_ = ["%d %d %d" % (ii,jj,kk) 
                       for ii in range(int(boxsize_[0])) 
                       for jj in range(int(boxsize_[1])) 
                       for kk in range(int(boxsize_[2]))]
        coordsincubes_ = dict() # (cube index : coordinates)
        for jj in range(self.numpts_):
            while len(emptycubes_) > 0:
                randindex_ = random.choice(emptycubes_)
                itr = 6
                while itr > 0:
                    randpos_ = self._Randposincube(randindex_, cubedim_)
                    if self._Nooverlap(randpos_, randindex_, boxsize_, 
                                               coordsincubes_):
                        ptscenters_.append(randpos_)
                        coordsincubes_[randindex_] = randpos_                     
                        emptycubes_.remove(randindex_)
                        break
                    else:
                        itr = itr - 1
                if itr > 0:
                    break
                else:
                    emptycubes_.remove(randindex_)
            if len(emptycubes_) == 0 and len(ptscenters_) != self.numpts_:
                raise ValueError("Percentfilled %g" % 
                                       (float(jj) / float(self.numpts_)))
        return ptscenters_


