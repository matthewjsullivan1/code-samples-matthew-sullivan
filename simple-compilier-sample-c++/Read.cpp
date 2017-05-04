
#include <iostream>
#include <algorithm>
#include <string>
#include <stdexcept>
#include <cstdlib>
#include <fstream>
#include "Convert.h"
#include "Read.h"
#include <sstream>
#include <iomanip>
#include <iterator>
#include <vector>
#include <map>
using namespace std;

Read::Read(std::ofstream & out , std::ifstream & in):output(out),input(in),
	                    RAMlocation(0),linecount(0)
{
}


size_t Read::ProcessFile() {
	char line[300];
	std::vector<string> dividedline;
	string binOPcode;
	string str;
	fpos<mbstate_t> filepos = Read::input.tellg();
	while(Read::input.getline(line, 300)){
		str = line;
		if(str.empty())
			continue;
		else if(str.size() >= 5 && (str.substr(0,5) == "DATA:")) {
			filepos = Read::input.tellg();
			while(Read::input.getline(line, 300)) {
				str = line;
				if(str.size() >= 5 && str.substr(0,5) == "CODE:") {
					Read::input.seekg(filepos);
					break;
				}
				Read::Processlinedata(str);
				filepos = Read::input.tellg();
			}
			continue;
		}
		else if(str.size() >= 5 && str.substr(0,5) == "CODE:") {
			Read::Findvarpos();
			while(Read::input.getline(line, 300)) {
				str = line;
				Read::Processline(str);
			}
			break;
		}
	}
	return Read::linecount;
}

void Read::Findvarpos() {
	int curlocation = Read::input.tellg();
	char line[300];
	string str;
	size_t count = Read::linecount;
	while(Read::input.getline(line, 300)){
		str = line;
		str = Read::Trim(str);
		size_t labelend = str.find_first_of(':');
		if(labelend != string::npos) {
			string label = str.substr(0, labelend);
			stringstream ss;
			ss << count;
			Read::labels.insert(std::pair<string,string>(label,ss.str()));
		}
		else if(str.length() >= 3 && (str.substr(0,3) == "POP" || str.substr(0,3) == "RET")){
			count = count + 2;
		}
		else if(str.length() >= 4 && str.substr(0,4) == "CALL"){
			count = count + 2;
		}
		else if(str.length() > 0) {
			count++;
		}
	}
	Read::input.clear();
	Read::input.seekg(curlocation);
}

string Read::Trim(string str) {
	if(str.empty())
		return str;
	//Remove "//" and leading and trailing whitespace
	size_t comments = str.find_first_of("//");
	str = str.substr(0,comments);
    string whitespace = " ";
	size_t strBegin = str.length();
	for(size_t ii=0;ii<str.length();++ii) {
		if(str[ii] != 32 && str [ii] != 9) {
			strBegin = ii;
			break;
		}
	}
	if(strBegin == str.length()) 
		return "";
	else {
		size_t strEnd = 0;
		for(size_t ii = str.length()-1;ii>=0;--ii) {
			if(str[ii] != 32 && str [ii] != 9) {
				strEnd = ii;
				break;
			}
		}
		size_t strRange = strEnd - strBegin + 1;
		return str.substr(strBegin, strRange);
	}
}

void Read::Processlinedata(string str) {
	str = Read::Trim(str);
	if(str.empty())
		return;
	std::vector<string> ret;
	size_t gap = str.find_first_of(" ");
	if(gap == string::npos) {
		stringstream ss;
		ss << Read::RAMlocation;
		ret.push_back("MOV");
		ret.push_back("A");
		ret.push_back(str);
		WriteOperation(ReturnOPcodes(ret));
		ret.clear();
		ret.push_back("MOV"); 
		ret.push_back("("+ ss.str() + ")");
		ret.push_back("A");
		WriteOperation(ReturnOPcodes(ret));
		Read::RAMlocation++;
		return;
	}
	else {
		//Save Variable position and write commands
		string var = str.substr(0,gap);
		stringstream ss;
		ss << Read::RAMlocation;
		Read::variables.insert(pair<string, string>(var, ss.str()));
		str = str.substr(gap+1, string::npos);
		str = Read::Trim(str);
		ret.push_back("MOV");
		ret.push_back("A");
		ret.push_back(str);
		WriteOperation(ReturnOPcodes(ret));
		ret.clear();
		ret.push_back("MOV");
		ret.push_back("("+ ss.str() + ")");
		ret.push_back("A");
		WriteOperation(ReturnOPcodes(ret));
		Read::RAMlocation++;
		return;
	}
}

void Read::Processline(string str){
	str = Read::Trim(str);
	if(str.empty()) 
		return;
	size_t poslabel = str.find(":");
	if(poslabel != string::npos) {
		return;
	}	
	std::vector<string> ret;
	size_t gap = str.find_first_of(" ");
	string command = str.substr(0,gap);
	if(command == "ADD" || command == "MOV" || command == "SUB" || command == "CMP" ||
	   command == "INC" || command == "DEC" || command == "JEQ" ||command == "JNE" || 
	   command == "JMP" || command == "JGE" || command == "JLT" || command == "PUSH" ||
	   command == "POP" || command == "IN" || command == "RET" || command == "CALL"){
			ret.push_back(command);
			str = str.substr(gap+1, string::npos);
			str = Read::Trim(str);
			size_t str1end = str.find_first_of(",");
			if (str1end == string::npos){
				ret.push_back(str);
			}
			else {
				string firstarg = str.substr(0,str1end);
				firstarg = Read::Trim(firstarg);
				ret.push_back(firstarg);
				string secarg = str.substr(str1end + 1, string::npos);
				secarg = Read::Trim(secarg);
				ret.push_back(secarg);
			}
			WriteOperation(ReturnOPcodes(ret));
			return;
	}
	else {
		return;
	}
}

string Read::ConvDirtoBinary(string dir) {
	std::map<string, string>::iterator loc = Read::variables.find(dir);
	if(loc == Read::variables.end()) {
		Convert conv(dir);
		return conv.Converttobin();
	}
	else {
		Convert conv(loc->second);
		return conv.Converttobin();
	}
}

std::vector<string> Read::ReturnOPcodes(std::vector<string> command) {
	string lit = "00000000";
	std::vector<string> OPret;
	if(command[0]=="MOV"){
		if(command[1]== "A") {
			if(command[2] == "B"){
				OPret.push_back(lit + "00000000000000001;// MOV A,B  ");
			}
			else if (command[2] == "(B)") {
				OPret.push_back( lit + "00000000000010100;// MOV A,(B) ");
			}
			else if (command[2][0] == '(') {
				string dir = command[2].substr(1,command[2].length()-2);
				OPret.push_back( Read::ConvDirtoBinary(dir) + "00000000000000101;// MOV A,(" + command[2] + ") ");
			}
			else {
				OPret.push_back(Read::ConvDirtoBinary(command[2]) + "00000000000000011;// MOV A, " + command[2] + " ");
				/*Convert conv(command[2]);
				OPret.push_back( conv.Converttobin() + "00000000000000011;// MOV A, " + command[2] + " ");*/
			}
		}
		else if (command[1] == "B") {
			if(command[2] == "A"){
				OPret.push_back( lit + "00000000000000010;// MOV B, A ");
			}
			else if (command[2] == "(B)") {
				OPret.push_back( lit + "00000000000010101;// MOV B, (B) ");
			}
			else if (command[2][0] == '(') {
				string dir = command[2].substr(1,command[2].length()-2);
				OPret.push_back( Read::ConvDirtoBinary(dir) + "00000000000000110;// MOV B,(" + command[2] + ")");
			}
			else {
				OPret.push_back( Read::ConvDirtoBinary(command[2]) + "00000000000000100;// MOV B, "+ command[2]+ " ");
			/*	Convert conv(command[2]);
				OPret.push_back( conv.Converttobin() + "00000000000000100;// MOV B, "+ command[2]+ " ");*/
			}
		}
		else if (command[1] == "(B)") {
			OPret.push_back( lit + "00000000000010110;// MOV (B), A ");
		}
		else if (command[1][0] == '('){
			if(command[2] == "A"){
				string dir = command[1].substr(1,command[1].length()-2);
				OPret.push_back( Read::ConvDirtoBinary(dir) + "00000000000000111;// MOV ("+command[1] +"), A ");
			}
			else if (command[2] == "B") {
				string dir = command[1].substr(1,command[1].length()-2);
				OPret.push_back( Read::ConvDirtoBinary(dir) + "00000000000001000;// MOV ("+command[1]+"), B ");
			}
		}
		else
			OPret.push_back( "          ");
	}
	else if (command[0] == "ADD" ){
		if(command.size()==2) {
			string dir = command[1].substr(1,command[1].length()-2);
			OPret.push_back( Read::ConvDirtoBinary(dir) + "00000000000011001;// ADD ("+command[1]+") ");
		}
		else if (command[1] == "A") {
			if(command[2] == "B") {
				OPret.push_back( lit + "00000000000001001;// ADD A, B ");
			}
			else if (command[2] =="(B)" ){
				OPret.push_back( lit + "00000000000011000;// ADD A, (B) ");
			}
			else if (command[2][0] =='('){
				string dir = command[2].substr(1,command[2].length()-2);
				OPret.push_back( Read::ConvDirtoBinary(dir) + "00000000000010111;// ADD A, ("+command[2]+") ");
			}
			else {
				OPret.push_back( Read::ConvDirtoBinary(command[2]) + "00000000000001011;// ADD A, " + command[2]+ " ");
				//Convert conv(command[2]);
				//OPret.push_back( conv.Converttobin() + "00000000000001011;// ADD A, " + command[2]+ " ");
			}
		}
		else if (command[1] == "B"){
			if(command[2] =="A") {
				OPret.push_back( lit + "00000000000001010;// ADD B, A ");
			}
			else {
				OPret.push_back( Read::ConvDirtoBinary(command[2]) + "00000000000001100;// ADD B, " + command[2]+ " ");
				//Convert conv(command[2]);
				//OPret.push_back( conv.Converttobin() + "00000000000001100;// ADD B, " + command[2]+ " ");
			}
		}
		else 
			OPret.push_back( "          ");
	}
	else if (command[0] == "SUB") {
		if(command.size()==2) {
			string dir = command[1].substr(1,command[1].length()-2);
			OPret.push_back( Read::ConvDirtoBinary(dir) + "00000000000011100;// SUB (" + command[1] + ") ");
		}
		else if (command[1] == "A") {
			if(command[2] == "B") {
				OPret.push_back( lit + "00000000000001101;// SUB A, B ");
			}
			else if (command[2] =="(B)" ){
				OPret.push_back( lit + "00000000000011011;// SUB A, (B) ");
			}
			else if (command[2][0] =='('){
				string dir = command[2].substr(1,command[2].length()-2);
				OPret.push_back( Read::ConvDirtoBinary(dir) + "00000000000011010;// SUB A, (" + command[2] + ") ");
			}
			else {
				OPret.push_back(Read::ConvDirtoBinary(command[2]) + "00000000000001111;// SUB A, " + command[2] + " ");
				//Convert conv(command[2]);
				//OPret.push_back( conv.Converttobin() + "00000000000001111;// SUB A, " + command[2] + " ");
			}
		}
		else if (command[1] == "B"){
			if(command[2] =="A") {
				OPret.push_back( lit + "00000000000001110;// SUB B, A ");
			}
			else {
				OPret.push_back(Read::ConvDirtoBinary(command[2]) + "00000000000010000;// SUB B, " + command[2] + " ");
				//Convert conv(command[2]);
				//OPret.push_back( conv.Converttobin() + "00000000000010000;// SUB B, " + command[2] + " ");
			}
		}
		else
			OPret.push_back( "          ");
	}
	else if (command[0] == "CMP") {
		if(command[1] == "A") {
			if(command[2] == "B") {
				OPret.push_back( lit + "00000000000100000;// CMP A,B");
			}
			else if (command[2][0] != '(') {
				OPret.push_back( Read::ConvDirtoBinary(command[2]) + "00000000000100001;// CMP A, " + command[2] + " ");
				//Convert conv(command[2]);
				//OPret.push_back( conv.Converttobin() + "00000000000100001;// CMP A, " + command[2] + " ");
			}
			else 
				OPret.push_back( "            ");
		}
		else 
			OPret.push_back( "            ");
	}
	else if (command[0] == "INC") {
		if(command[1] == "A") {
			Convert conv("1");
			OPret.push_back( conv.Converttobin() + "00000000000011101;// INC A ");
		}
		else if(command[1] == "B") {
			OPret.push_back( lit + "00000000000010001;// INC B ");
		}
		else {
			OPret.push_back( "          ");
		}
	}
	else if (command[0] == "DEC") {
		if(command[1] == "A") {
			Convert conv("1");
			OPret.push_back( conv.Converttobin() +"00000000000011110;// DEC A ");
		}
		else {
			OPret.push_back( "          ");
		}
	}
	else if (command[0] == "JEQ") {
		std::map<string,string>::iterator loc = Read::labels.find(command[1]);
		Convert conv(loc->second);
		OPret.push_back( conv.Converttobin() + "00000000000010010;// JEQ " + command[1] + " ");
	}
	else if (command[0] == "JNE" ){
		std::map<string,string>::iterator loc = Read::labels.find(command[1]);
		Convert conv(loc->second);
		OPret.push_back( conv.Converttobin() + "00000000000011111;// JNE " + command[1] + " ");
	}
	else if (command[0] == "JMP") {
		std::map<string,string>::iterator loc = Read::labels.find(command[1]);
		Convert conv(loc->second);
		OPret.push_back( conv.Converttobin() + "00000000000010011;// JMP " + command[1] + " ");
	}
	else if (command[0] == "JGE") {
		std::map<string,string>::iterator loc = Read::labels.find(command[1]);
		Convert conv(loc->second);
		OPret.push_back( conv.Converttobin() + "00000000000100010;// JGE " + command[1] + " ");
	}
	else if (command[0] == "JLT") {
		std::map<string,string>::iterator loc = Read::labels.find(command[1]);
		Convert conv(loc->second);
		OPret.push_back( conv.Converttobin() + "00000000000100011;// JLT " + command[1] + " ");
	}
	else if (command[0] == "PUSH") {
		if(command[1] == "A") {
			OPret.push_back( lit + "00000000000100100;// PUSH A");
		}
		else if(command[1] == "B") {
			OPret.push_back( lit + "00000000000100101;// PUSH B");
		}
		else
			OPret.push_back( "         ");
	}
	else if (command[0] == "POP") {
		if(command[1] == "A") {
			OPret.push_back( lit + "00000000000100110;// POP A");
			OPret.push_back(lit + "00000000000100111;// POP A");

		}
		else if(command[1] == "B") {
			OPret.push_back( lit + "00000000000101000;// POP B");
			OPret.push_back(lit + "00000000000101001;// POP B");
		}
		else
			OPret.push_back( "         ");
	}
	else if (command[0] == "IN") {
		if(command[1] == "A") {
			OPret.push_back( lit + "00000000000101010;// IN A");
		}
		else if(command[1] == "B") {
			OPret.push_back( lit + "00000000000101011;// IN B");
		}
		else
			OPret.push_back( "         ");
	}
	else if (command[0] == "RET") {
		OPret.push_back( lit + "00000000000101100;// RET");
		OPret.push_back(lit + "00000000000101101;// RET");
	}
	else if (command[0] == "CALL") {
		std::map<string,string>::iterator loc = Read::labels.find(command[1]);
		Convert conv(loc->second);
/*		stringstream ss;
		ss << (Read::linecount+2);
		Convert conv2(ss.str()); */
		OPret.push_back(lit + "00000000000101110;// CALL " + command[1] + " ");
		OPret.push_back(conv.Converttobin() + "00000000000101111;// CALL " + command[1] + " ");
	}
	else {
		OPret.push_back( "               ");
	}
	return OPret;
}

void Read::WriteOperation(std::vector<string> oper) {
	for(size_t jj=0; jj<oper.size(); ++jj) {
		Read::output << "Mem[" << Read::linecount << "] = 25'b" ;
		for(size_t ii=0; ii<oper[jj].length(); ++ii) {
			Read::output << oper[jj][ii];
		}
		Read::output << "\n";
		Read::linecount += 1;
	}
}