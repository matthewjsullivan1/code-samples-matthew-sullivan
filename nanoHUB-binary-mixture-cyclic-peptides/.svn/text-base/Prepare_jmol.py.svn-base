import os
class Prepare_jmol:
    def __init__(self, origxyz_):
        self.origxyz_ = origxyz_

    def Write_jmol(self, newxyz_):
        output = open(newxyz_, 'w')
        str = """
spacefill ON 
spacefill 120% 
zoom ON
zoom 80
animation ON 
animation frame _lastFrame"""
     
        output.write(str)
        output.close()

    def Write_newxyz(self, newxyz_):
        input = open(self.origxyz_, 'r')
        output = open(newxyz_, 'w')
        for line in input:
            line = line.split()   
            length = len(line)
            if length > 1 and line[0] == "1":
                line[0] = "O"
            elif length > 1 and line[0] == "3":
                line[0] = "N"
            line.append("\n")
            output.write(" ".join(line))
        input.close()
        output.close()
        os.remove("./" + self.origxyz_)

