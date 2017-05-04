#Processes a csv file from mySQL database and writes two HTML tables
#First HTML table contains receipts for each order, sorted by estimated pickup time
#Second HTML table contains order matrix for each item available on the menu 


import csv
from tabulate import tabulate
from datetime import time

with open('wp_wppizza_orders.csv', 'rb') as f:
    orders = []
    reader = csv.reader(f)
    for row in reader:
        if row[10] == 'COMPLETED':
            orders.append([row[0], row[4], row[5]])

receipt = [] 
for [orderid, customer, orderinfo] in orders:

    #Split customerinfo before comment section and after comment section
    commentstitle = 'Comments:'
    customerinfo = customer.split(commentstitle, 1)[0].strip() 
    commentsection = customer.split(commentstitle, 1)[1].strip()
    commentsection = '\n'.join([line.strip() for line in commentsection.split('\n')])
    customerinfo = [line.split(':',1)[0].strip() + ': ' + line.split(':',1)[1].strip() for line in customerinfo.split('\n') if ':' in line]
    customerinfo.append('Comments:\n' + commentsection)

    #Makes "Name: John Doe" bold
    customerinfo[0] = '<strong>' + customerinfo[0] + '</strong>'

    #Extract pickup time from customer section to be used for sorting later
    pickupline = ''
    pickuptitle = 'estimated pickup time'
    for line in customerinfo:
        if pickuptitle in line.lower():
            pickupline = line
            break

    customerinfo.remove(pickupline)
    pickupstring = pickupline.split('):')[1].strip()

    #If a time interval separated by '-' was given, only process the earlier time
    pickupearlytime = pickupstring.split('-')[0]

    #If time given appears to be pm, then convert to 24 hour clock
    def convert24hour(hour, minute=0):
        if hour in range(1,8):
            hour = hour + 12
        return time(hour, minute)

    def reverse(astring):
        return ''.join(astring[i] for i in reversed(range(len(astring))))
    
    #sample findfirstdigitset('maybe 4 30 or later 5ish') would return '430' 
    def findfirstdigitset(inputstring):
        sethasbegun = False 
        digitset = []
        for ch in inputstring:
            if ch.isdigit() and not sethasbegun:
                sethasbegun = True
                digitset.append(ch)
            elif ch.isdigit() and sethasbegun: 
                digitset.append(ch)
            elif not (ch.isdigit() or ch == ' ') and sethasbegun:
                break
        return ''.join(digitset)
 
   
    #Default pickup time is 00:00 so unparseable data will be the first orders
    pickuptime = time.min
    if ':' in pickupearlytime:
        #only process closest digits from ':'
        #need to reverse string to parse hour data that is closest to ':'
        pickuphour = findfirstdigitset(reverse(pickupearlytime.split(':')[0].strip()))
        pickuphour = reverse(pickuphour)
        pickupmin = findfirstdigitset(pickupearlytime.split(':')[1].strip())
        
        pickuphour = int(pickuphour)
        pickupmin = int(pickupmin)

        if pickuphour in range(24) and pickupmin in range(60):
            pickuptime = convert24hour(pickuphour, pickupmin)
        elif pickuphour in range(24):
            pickuptime = convert24hour(pickuphour)

    else:
        pickuptmp = findfirstdigitset(pickupearlytime)
        if 1 <= len(pickuptmp) <= 2:
            pickuphour = int(pickuptmp[0:2])
            if pickuphour in range(24):
                pickuptime = convert24hour(pickuphour)
        elif len(pickuptmp) == 3:
            pickuphour = int(pickuptmp[0])
            pickupmin = int(pickuptmp[1:3])
            if pickuphour in range(24) and pickupmin in range(60):
                pickuptime = convert24hour(pickuphour, pickupmin)
        elif len(pickuptmp) == 4:
            pickuphour = int(pickuptmp[0:2])
            pickupmin = int(pickuptmp[2:4])
            if pickuphour in range(24) and pickupmin in range(60):
                pickuptime = convert24hour(pickuphour, pickupmin)
      
    #Replace newline markers with html <br> 
    customerinfo = '<br>'.join(customerinfo)
    customerinfo = customerinfo.replace('\n', '<br>')

    #Double empty line separating the ordered items and prices
    tmp = orderinfo.split('\n\n')
    orderitems = tmp[0].strip()
    orderpricetotals = tmp[1].strip()

    itemslist = [line.split(']',1)[0] + ']' for line in orderitems.split('\n') if ']' in line]
    itemslist = '<br>'.join(item.strip() for item in itemslist)
    itemslist = itemslist.replace('&#038;', '&')

    pricelist = [line.split('$')[0].strip() + ' $' + line.split('$')[1].strip() for line in orderpricetotals.split('\n') if '$' in line]
    pricelist = '<br>'.join(item for item in pricelist)
   
    receipt.append([pickuptime, pickupstring, orderid, customerinfo, itemslist, pricelist])

#sort by pickuptime then name
receipt.sort(key=lambda x: (x[0],x[3].lower()))
#receipt does not include parsed pick-up time that was used for sorting
receipt = [orders[1:] for orders in receipt] 

with open('testing.html','w') as f:
    f.write('<head><style media="all" type ="text/css">') 
    f.write('@media print {tr {page-break-inside:avoid;} table {font-size: 1rem;}}')
    f.write('table {width:100%; border-collapse: collapse;font-size: 1rem;}')
    f.write('table, td {border: 1px solid black;}')
    f.write('tr {border: 2px dashed;}')
    f.write('td {padding: 10px;}')
    f.write('td:nth-child(5) {text-align:right;}')
    f.write('td:nth-child(-n+2) {white-space: nowrap;}')
    f.write('td:nth-child(n+2) {white-space: nowrap;}')
    f.write('tr:nth-child(odd) {background-color: #DCDCDC;}')
    f.write('</style></head>')
    f.write(tabulate(receipt, tablefmt="html"))


allordereditemswithquantity = [item[3].split('<br>') for item in receipt]

#For every ordered item, modifies ['1x Coconut Chicken Curry [6.00]'] to ['Coconut Chicken Curry']
allordereditems = [item.split('x')[1].strip() for orderitem in allordereditemswithquantity for item in orderitem]
allordereditems = [item.split('[')[0].strip() for item in allordereditems]

setmainitems = set()
setsideitems = set()

#Flags to add rice, dal, and cauliflower if entree or paleo is ordered
addrice = False
addsidecurry = False
addcauli = False

#set identifier for entree, paleo, and curry
entree = 'Entree'
paleo = 'Paleo'
curry = 'Curry'
#set sizes for side items
drinksize = '12 oz'
naansize = '2 pc'
sidesize = 'Side'
#Remove size from item name
for item in allordereditems:
    itemnametmp = item
    if entree in item:
        #if entree added also need to add rice & dal to side items
        itemnametmp = itemnametmp.split(entree)[0]
        setmainitems.add(itemnametmp.strip())
        addrice = True
        addsidecurry = True
    elif paleo in item: 
        #if paleo added also need to add cauliflower to side items
        itemnametmp = itemnametmp.split(paleo)[0]
        setmainitems.add(itemnametmp.strip())
        addcauli = True
    elif curry in item:
        itemnametmp = itemnametmp.split(curry)[0]
        setmainitems.add(itemnametmp.strip())
    elif (drinksize in item) or (naansize in item) or (sidesize in item):
        itemnametmp = itemnametmp.split(drinksize)[0]
        itemnametmp = itemnametmp.split(naansize)[0]
        itemnametmp = itemnametmp.split(sidesize)[0]
        setsideitems.add(itemnametmp.strip())


mainitems = [item for item in setmainitems]
sideitems = [item for item in setsideitems]

#Finds if Chana Masala has been ordered
chanaexists = False
chana = 'Chana Masala'
for item in mainitems:
    if chana in item: 
        chanaexists = True

#Finds if Dal has been ordered
dalexists = False
daltag = 'Dal'
for item in mainitems:
    if daltag in item:
        dalexists = True

#If dal and chana masala have not been ordered, assume that side curry is dal
#Then add dal to main items
if not dalexists and not chanaexists and addsidecurry:
    mainitems.append(daltag)


mainitemheaders = []
cocotag = 'Coco'
tikkatag = 'Tikka'
for item in mainitems:
    if cocotag in item[0:len(cocotag)]:
        mainitemheaders.append(cocotag)
    elif tikkatag in item:
        mainitemheaders.append(tikkatag) 
    else:
        mainitemheaders.append(item.split()[0])

#Puts Cauliflower to front of sideitems
cauliflowerexists = False
caulitag = 'Cauli'
for item in sideitems:
    if caulitag in item:
        cauliflowerexists = True

if cauliflowerexists:
    #find index of cauliflower, pop element, and insert cauliflower at front
    index = 0 
    while caulitag not in sideitems[index]:
        index += 1
    cauliname = sideitems.pop(index)
    sideitems.insert(0, cauliname)
elif not cauliflowerexists and addcauli:
    sideitems.insert(0, caulitag)

#Puts Rice to front of sideitems
riceexists = False
ricetag = 'Rice'
for item in sideitems:
    if ricetag in item:
        riceexists = True

if riceexists:
    #find index of rice, pop element, and insert rice at front
    index = 0 
    while ricetag not in sideitems[index]:
        index += 1
    ricename = sideitems.pop(index)
    sideitems.insert(0, ricename)
elif not riceexists and addrice:
    sideitems.insert(0, ricetag)

#Parse header name from item name
lassitag = 'Lassi'
saladtag = 'Salad'
sideitemheaders = []
for item in sideitems:
    if ricetag in item:
        sideitemheaders.append(ricetag)
    elif caulitag in item:
        sideitemheaders.append(caulitag)
    elif lassitag in item:
        sideitemheaders.append(lassitag)
    elif saladtag in item:
        sideitemheaders.append(saladtag)
    else: 
        sideitemheaders.append(item.split()[0])


itemheaders = mainitemheaders + sideitemheaders
allitemnames = mainitems + sideitems
nameheaderlist = zip(allitemnames, itemheaders)

customerheaders = ['Time','ID', 'Name', '#', 'Comments','Subtotal']
allheaders = customerheaders + itemheaders 

 

def findheader(itemname, itemheaderlist):
    for (name, header) in itemheaderlist:
        if name in itemname:
            return header

#Assume sidecurry is dal unless chana masala has been ordered
if addsidecurry:
    sidecurrytag = daltag
    if chanaexists and not dalexists:
        for (name, header) in nameheaderlist:
             if chana in name:
                 sidecurrytag = header


ordermatrix = []
for order in receipt:
    matrixrow = [order[0],order[1]]
    matrixrow.append(order[2].split('Name:')[1].split('</strong>',1)[0].strip()) 
    matrixrow.append(order[2].split('Number:')[1].split('<br>',1)[0].strip()) 
    matrixrow.append(order[2].split('Comments:<br>')[1].strip()) 
    matrixrow.append('$' + order[4].split('$',1)[1].split('<br>',1)[0].strip())
    itemcount = {x: 0 for x in itemheaders}
    for item in order[3].split('<br>'):
        itemquantity = int(item.split('x')[0])
        itemname = item.split('x')[1]
        if entree in itemname:
            #Each entree has portion of dal and rice
            itemcount[sidecurrytag] += itemquantity
            itemcount[ricetag] += itemquantity         
        elif paleo in itemname:
            #Each paleo has double portion of curry and cauliflower
            itemcount[caulitag] += itemquantity
            itemcount[findheader(itemname, nameheaderlist)] += itemquantity

        itemcount[findheader(itemname, nameheaderlist)] += itemquantity

    for header in itemheaders:
        if itemcount[header] == 0:
            matrixrow.append('') 
        else:
            matrixrow.append(itemcount[header]) 

    ordermatrix.append(matrixrow)

totalsrow = [''] * (len(customerheaders)-2)  
totalsrow.append('TOTALS')
totalsrowdata = [0]*(len(itemheaders)+1)
#Sum of Subtotals
totalsrowdata[0] = '$' + str(sum([float(order[5].split('$')[1]) for order in ordermatrix]))

#Sum of items for each itemheader
for row in ordermatrix:
    for ii in range(len(itemheaders)):
        if row[len(customerheaders)+ii] is not '':
            totalsrowdata[ii+1] += row[len(customerheaders)+ii]
    
totalsrow.extend(totalsrowdata) 
ordermatrix.append(totalsrow)

with open('testingmatrix.html','w') as f:
    customerheaderlength = str(len(customerheaders))
    riceposition = str(len(customerheaders) + len(mainitemheaders) + 1)
    f.write('<head><style media="all" type ="text/css">') 
    f.write('@media print {tr {page-break-inside:avoid;} thead {display: table-header-group;}}')
    f.write('table {width:100%; border-collapse: collapse;font-size: 0.8rem;}')
    f.write('table,td, th {border: 1px solid black;}')
    f.write('tr {border: 2px solid black;}')
    f.write('td {padding: 0.8em;}')
    f.write('td:nth-child(n+' + customerheaderlength + ') {text-align: center;}')
    f.write('thead {font-size: 1.5em;}')
    f.write('td:nth-child(n+' + customerheaderlength + '){font-size: 1.3em;}')
    f.write('td:nth-child(' + riceposition + '){border-style:dashed;border-width:2px;}')
    f.write('tr:nth-child(odd) {background-color: #DCDCDC;}')
    f.write('tr:last-child {font-weight: bold;}')
    f.write('</style></head>')
    f.write(tabulate(ordermatrix, headers=allheaders, tablefmt="html"))



