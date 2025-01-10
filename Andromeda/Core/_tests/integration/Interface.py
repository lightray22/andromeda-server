
import abc, os, requests, subprocess, json
from urllib.parse import urlencode

# https://requests.readthedocs.io/en/latest/
# https://docs.python.org/3/library/subprocess.html

from TestUtils import *

class Interface():
    """ Base class for API interfaces """
    def __str__(self):
        return self.__class__.__name__
    
    apiCount:int = 0
    isPriv:bool = None

    @abc.abstractmethod
    def run(self, app:str, action:str, params:dict[str,str]={}, files:dict[str,tuple[str,str]]={}, isJson:bool=True, install:bool=False):
        """ Runs a command on the interface with the given param set"""
        pass

    def handleResp(self, respdata:bytes, isJson:bool, label:str):
        """ Returns a decoded response """
        if isJson:
            try:
                respdata = respdata.decode('utf-8')
                respdata = json.loads(respdata)
            except:
                print("BAD JSON", os.linesep, respdata); raise # return
            if self.verbose >= 2: 
                print(label,"->", json.dumps(respdata, indent=4))
        else:
            if self.verbose >= 2: 
                print(label, "-> got", len(respdata), "bytes")
                print(respdata)
        return respdata

class HTTP(Interface):
        
    def __init__(self, config, verbose:int=0):
        self.url = config
        self.verbose = verbose
        self.isPriv = False

    def run(self, app:str, action:str, params:dict[str,str]={}, files:dict[str,tuple[str,str]]={}, isJson:bool=True, install:bool=False):
        if self.verbose >= 1:
            label = 'INST' if install else 'API'
            print(label,'<-',app,action,params)

        get = {'_app':app,'_act':action}
        return self.httpRun(get=get, post=params, files=files, isJson=isJson, install=install)[1]

    def httpRun(self, get:dict[str,str]={}, post:dict[str,str]={}, files:dict[str,tuple[str,str]]={}, headers:dict[str,str]={}, auth:tuple[str,str]=(), isJson:bool=True, install:bool=False):
        """ Runs an HTTP request with extra HTTP-specific options, returns (code, data) """
        self.apiCount += 1

        get = get.copy()
        post = post.copy()
        headers = headers.copy()
        for key,val in get.items():
            if val is None: get[key] = ""
        for key,val in post.items():
            if val is None: post[key] = ""
        for key,val in headers.items():
            if val is None: headers[key] = ""

        url = self.url+("/install.php" if install else "/index.php")

        if self.verbose >= 2: 
            httpstr = f"URL:{url}?"+urlencode(get)
            if headers:
                httpstr += " HEADERS:"+str(headers)
            if post:
                httpstr += " POSTBODY:"+str(post)
            print("(HTTP)",httpstr)

        resp = requests.post(url,params=get,data=post,files=files,headers=headers,auth=auth)
        if isJson: assert 'application/json' == resp.headers.get('content-type')

        label = 'INST' if install else 'API'
        return (resp.status_code, self.handleResp(resp.content, isJson, label))

class CLI(Interface):    

    def __init__(self, config, verbose:int=0):
        self.path = config
        self.verbose = verbose
        self.isPriv = True

        if not os.path.exists(self.path+'/index.php'):
            raise Exception("cannot find index.php")

    def run(self, app:str, action:str, params:dict[str,str]={}, files:dict[str,tuple[str,str]]={}, isJson:bool=True, install:bool=False):
        if self.verbose >= 1:
            label = 'INST' if install else 'API'
            print(label,'<-',app,action,params)
        
        args = ['--debug','sensitive'] # in case of error
        if isJson: args += ['--outmode','json']
        
        args += [app, action]
        for key, value in params.items():
            args.append("--"+key)
            if value is not None:
                args.append(str(value))

        stdin=None
        assert(len(files) <= 1)
        # TODO FUTURE this could work with > 1 - just input one file after the next - but how is order in the server determined?
        for key,(name,data) in files.items():
            args.append(f'--{key}-')
            args.append(name)
            stdin=data
        
        return self.cliRun(args=args, stdin=stdin, isJson=isJson, install=install)[1]

    def cliRun(self, args:list[str]=[], stdin:str=None, env:dict[str,str]={}, isJson:bool=False, install:bool=False):
        """ Runs a CLI request with extra CLI-specific options, returns (code, data) """
        self.apiCount += 1

        path = self.path+("/install.php" if install else "/index.php")
        command = ['php', path] + args

        if self.verbose >= 2: 
            cmdstr = " ".join(command)
            for key,val in env.items():
                cmdstr = f"{key}={val} {cmdstr}"
            if stdin is not None: 
                cmdstr = f"echo {stdin} | {cmdstr}"
            print("(CLI)",cmdstr)

        if stdin is not None: 
            stdin = bytes(stdin,'utf-8')
        
        env0 = os.environ.copy(); env0.update(env)
        resp = subprocess.run(command, input=stdin, capture_output=True, env=env0)

        label = 'INST' if install else 'API'
        return (resp.returncode, self.handleResp(resp.stdout, isJson, label))
