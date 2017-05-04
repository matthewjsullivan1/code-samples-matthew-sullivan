def Process_output(filename, category, timestep):
    Output = open(filename, 'r')
    timelist_ = []
    ylist_ = [] 
    for line in Output:
        list = line.split()
        if "Step" in list: 
            index = list.index("Step")
            timelist_.append(float(list[index + 1]) * timestep / 1000000)
        if category in list: 
            index = list.index(category)
            ylist_.append(list[index+2])
    Output.close()
    returnlist_ = []
    for ii in range(len(timelist_)):
        returnlist_.append("%f %s" % (timelist_[ii], ylist_[ii]))
    return  '\n'.join(returnlist_)
